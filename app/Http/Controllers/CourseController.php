<?php

namespace App\Http\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use Exception;
use Google_Client;
use Google_Service_Classroom;
use Google_Service_Classroom_Announcement;
use Google_Service_Classroom_Student;
use Google_Service_Exception;
use Illuminate\Support\Facades\Cache;
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
        $courses = $this->courseRepository->findCourseByTeacherId($user['id']);
        return response()->json($courses, Response::HTTP_OK);
    }

    public function getCourseByUuid(Request $request, $uuid)
    {
        $course = $this->courseRepository->findCourseByUuid($uuid);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($course, Response::HTTP_OK);
    }

    public function getCourseById(Request $request, $id)
    {
        $user = Auth::user();
        $course = $this->courseRepository->findCourseById($id);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        if ($course['teacher_id'] != $user['id']) {
            return response()->json(['error' => 'you_cannot_access_course'], Response::HTTP_FORBIDDEN);
        }
        return response()->json($course, Response::HTTP_OK);
    }

    public function getGCLCourse(Request $request)
    {
        $user = Auth::user();
        $token = $this->userRepository->getUserAccessToken($user['id']);
        $client = new Google_Client();
        $client->setAccessToken($token);
        $service = new Google_Service_Classroom($client);

        $params = array(
            'courseStates' => ['ACTIVE'],
            'teacherId' => $user['google_user_id']
        );
        try {
            $response = $service->courses->listCourses($params);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() == 403) {
                return response()->json(['error' => json_decode($e->getMessage(), true)['error']['message']], Response::HTTP_FORBIDDEN);
            }
            throw $e;
        }
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
            }
            if ($e->getCode() == 403) {
                return response()->json(['error' => json_decode($e->getMessage(), true)['error']['message']], Response::HTTP_FORBIDDEN);
            }
            throw $e;
        }
        $students = $this->courseRepository->getStudentByGoogleClassroomId($googleClassroomId);
        if (!count($students)) {
            $this->listGoogleCoursesStudents($googleClassroomId, $token);
        }
        $course = $this->courseRepository->createCourse([
            'google_classroom_id' => $googleClassroomId,
            'name' => $courseName,
            'teacher_id' => $user['id'],
            'start_timestamp' => date('c', strtotime($startTimestamp)),
            'late_time' => $lateTime,
            'expire_time' => $expireTime,
            'uuid' => (string)Str::uuid()]);
        return response()->json($course, Response::HTTP_CREATED);
    }

    public function shareCourseWithPost(Request $request, $courseId)
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
        $link = env("FRONTEND_URL") . '/checkin/' . (config('google.MAPPING.' . str_replace('.', '-', $user['domain'])) ?? config('google.MAPPING.*')) . '/' . $course['uuid'];
        try {
            $response = Http::asForm()->post(env("CSC_SHORT_API"), [
                'signature' => env("CSC_SHORT_SIGNATURE"),
                'action' => 'shorturl',
                'format' => 'simple',
                'url' => $link,
            ]);
            if ($response->ok()) {
                $link = $response->body();
            }
        } catch (Exception $e) {
        }
        $startTime = Carbon::createFromFormat('c', $course['start_timestamp'])->format('Y-m-d H:i');
        $lateTime = Carbon::createFromFormat('c', $course['start_timestamp'])->add(CarbonInterval::createFromFormat('H:i:s', $course['late_time']))->format('H:i');
        $expireTime = Carbon::createFromFormat('c', $course['start_timestamp'])->add(CarbonInterval::createFromFormat('H:i:s', $course['expire_time']))->format('H:i');
        $msg = '????????????????????????????????????????????? ' . $link . '
????????????????????????????????? ' . $startTime . '~' . $expireTime . '???' . $lateTime . '???????????????????????????
1. ???????????????????????????????????????????????????????????????????????????
2. ??????????????????????????????????????????????????? https://dstw.dev/checkin';
        $announcement = new Google_Service_Classroom_Announcement(array(
            'text' => $msg,
            'state' => 'PUBLISHED'
        ));
        try {
            $response = $service->courses_announcements->create($course['google_classroom_id'], $announcement);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() == 403) {
                return response()->json(['error' => json_decode($e->getMessage(), true)['error']['message']], Response::HTTP_FORBIDDEN);
            }
            throw $e;
        }
        return response()->json(['link' => $link], Response::HTTP_CREATED);
    }

    public function shareCourse(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = $this->courseRepository->findCourseById($courseId);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        if ($course['teacher_id'] != $user['id']) {
            return response()->json(['error' => 'you_cannot_share_this_course'], Response::HTTP_FORBIDDEN);
        }
        $link = env("FRONTEND_URL") . '/checkin/' . (config('google.MAPPING.' . str_replace('.', '-', $user['domain'])) ?? config('google.MAPPING.*')) . '/' . $course['uuid'];
        try {
            $response = Http::asForm()->post(env("CSC_SHORT_API"), [
                'signature' => env("CSC_SHORT_SIGNATURE"),
                'action' => 'shorturl',
                'format' => 'simple',
                'url' => $link,
            ]);
            if ($response->ok()) {
                $link = $response->body();
            }
        } catch (Exception $e) {
        }

        return response()->json(['link' => $link], Response::HTTP_CREATED);
    }

    public function syncStudent(Request $request, $courseId)
    {
        $course = $this->courseRepository->findCourseById($courseId);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        Cache::tags('checkin')->forget($courseId);
        $user = Auth::user();
        $token = $this->userRepository->getUserAccessToken($user['id']);
        $this->listGoogleCoursesStudents($course['google_classroom_id'], $token);

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function endCourse(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = $this->courseRepository->findCourseById($courseId);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        if ($course['teacher_id'] != $user['id']) {
            return response()->json(['error' => 'you_cannot_share_this_course'], Response::HTTP_FORBIDDEN);
        }
        $time = Carbon::createFromFormat('c', $course['start_timestamp'])->diff(Carbon::now())->format('%H:%i:%s');
        $this->courseRepository->updateCourse($courseId, ['expire_time' => $time]);

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    private function listGoogleCoursesStudents($googleClassroomId, $token)
    {
        $client = new Google_Client();
        $client->setAccessToken($token);
        $service = new Google_Service_Classroom($client);
        $googleStudents = array();
        $pageToken = NULL;
        do {
            $params = array(
                'pageSize' => 100,
                'pageToken' => $pageToken
            );
            try {
                $response = $service->courses_students->listCoursesStudents($googleClassroomId, $params);
            } catch (Google_Service_Exception $e) {
                if ($e->getCode() == 403) {
                    return response()->json(['error' => json_decode($e->getMessage(), true)['error']['message']], Response::HTTP_FORBIDDEN);
                }
                throw $e;
            }
            $googleStudents = array_merge($googleStudents, $response->students);
            $pageToken = $response->nextPageToken;
        } while (!empty($pageToken));
        $newStudents = array();
        foreach ($googleStudents as $student) {
            $newStudents[] = ['google_classroom_id' => $googleClassroomId, 'google_user_id' => $student['userId'], 'name' => $student['profile']['name']['fullName']];
        }
        $this->courseRepository->setStudentByGoogleClassroomId($googleClassroomId, $newStudents);
    }
}
