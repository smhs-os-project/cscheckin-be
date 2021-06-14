<?php

namespace App\Http\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Google_Client;
use Google_Service_Classroom;
use Google_Service_Classroom_Announcement;
use Google_Service_Classroom_Student;
use Google_Service_Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class CourseController extends Controller
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var CourseRepository
     */
    private $courseRepository;

    public function __construct(UserRepository $userRepository, CourseRepository $courseRepository)
    {
        $this->userRepository = $userRepository;
        $this->courseRepository = $courseRepository;
    }

    public function getCourse(Request $request)
    {
        $user = Auth::user();
        $courses = $this->courseRepository->allCourse();
        return response()->json($courses, Response::HTTP_OK);
    }

    public function getGCLCourse(Request $request)
    {
        $user = Auth::user();
        $token = $this->userRepository->getUserAccessToken($user['id']);
        $client = new Google_Client();
        $client->setAccessToken($token);
        $service = new Google_Service_Classroom($client);

        $params = array(
            'courseStates' => ['ACTIVE']
        );
        $response = $service->courses->listCourses($params);
        $courses = $response->courses;
        $result = array();
        foreach ($courses as $course) {
            $result[] = ['google_classroom_id' => $course['id'], 'name' => $course['name']];
        }

        return response()->json($result, Response::HTTP_OK);
    }

    public function createCourse(Request $request, $googleClassroomId)
    {
        $startTimestamp = $request->input('start_timestamp');
        $lateTime = $request->input('late_time');
        $expireTime = $request->input('expire_time');
        $user = Auth::user();
        $token = $this->userRepository->getUserAccessToken($user['id']);
        $client = new Google_Client();
        $client->setAccessToken($token);
        $service = new Google_Service_Classroom($client);
        try {
            $courseName = $service->courses->get($googleClassroomId)['name'];
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() == 404) {
                return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
            } else {
                throw $e;
            }
        }
        $students = $this->courseRepository->getStudentByGoogleClassroomId($googleClassroomId);
        if (!count($students)) {
            $response = $service->courses_students->listCoursesStudents($googleClassroomId);
            $newStudents = array();
            foreach ($response->students as $student) {
                $newStudents[] = ['google_classroom_id' => $googleClassroomId, 'google_user_id' => $student['userId'], 'name' => $student['profile']['name']['fullName'], 'email' => $student['profile']['emailAddress']];
            }
            $this->courseRepository->setStudentByGoogleClassroomId($googleClassroomId, $newStudents);
        }
        $course = $this->courseRepository->createCourse([
            'google_classroom_id' => $googleClassroomId,
            'name' => $courseName,
            'teacher_id' => $user['id'],
            'start_timestamp' => $startTimestamp,
            'late_time' => $lateTime,
            'expire_time' => $expireTime,
            'uuid' => (string)Str::uuid()]);
        return response()->json($course, Response::HTTP_OK);
    }

    public function shareCourse(Request $request, $courseId)
    {
        $user = Auth::user();
        $token = $this->userRepository->getUserAccessToken($user['id']);
        $client = new Google_Client();
        $client->setAccessToken($token);
        $service = new Google_Service_Classroom($client);
        $course = $this->courseRepository->findCourseById($courseId);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        if ($course['teacher_id'] != $user['id']) {
            return response()->json(['error' => 'you_cannot_share_this_course'], Response::HTTP_FORBIDDEN);
        }
        $link = env("FRONTEND_URL") . '/' . config('google.MAPPING.' . $user['domain']) . '/' . $course['uuid'];
        $response = Http::post(env("CSC_SHORT_API"), [
            'signature' => env("CSC_SHORT_SIGNATURE"),
            'action' => 'shorturl',
            'format' => 'simple',
            'url' => $link,
        ]);
        $link = $response->body();
        $msg = '嗨各位同學，這節課的簽到連結如下：
' . $link;
        $announcement = new Google_Service_Classroom_Announcement(array(
            'text' => $msg,
            'state' => 'PUBLISHED'
        ));
        $response = $service->courses_announcements->create($course['google_classroom_id'], $announcement);
        return response()->json([], Response::HTTP_CREATED);
    }
}
