<?php

namespace App\Http\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Google_Client;
use Google_Service_Classroom;
use Google_Service_Classroom_Student;
use Google_Service_Exception;
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

    public function createCourse(Request $request)
    {
        $googleClassroomId = $request->input('google_classroom_id');
        $startTimestamp = $request->input('start_timestamp');
        $lateTime = $request->input('late_time');
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
            'uuid' => (string)Str::uuid()]);
        return response()->json($course, Response::HTTP_OK);
    }
}
