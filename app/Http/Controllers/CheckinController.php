<?php

namespace App\Http\Controllers;

use App\Repositories\CheckinRepository;
use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckinController extends Controller
{
    /**
     * @var CourseRepository
     */
    private $courseRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var CheckinRepository
     */
    private $checkinRepository;

    public function __construct(UserRepository $userRepository, CourseRepository $courseRepository, CheckinRepository $checkinRepository)
    {
        $this->userRepository = $userRepository;
        $this->courseRepository = $courseRepository;
        $this->checkinRepository = $checkinRepository;
    }

    public function getCourseCheckin(Request $request, $courseId)
    {
        $course = $this->courseRepository->findCourseById($courseId);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        $allStu = $this->courseRepository->getStudentByGoogleClassroomId($course['google_classroom_id'])->sortBy('name');
        $checkinStu = $this->checkinRepository->findCheckinByCourseId($courseId);
        $checkedIn = array();
        $notCheckedIn = array();
        foreach ($allStu as $item) {
            $uu = $this->userRepository->findUserByGoogleUserId($item['google_user_id']);
            $si = $this->userRepository->getStudentInfo($uu['id']);
            $checkin = $checkinStu->where('student_id', $uu['id'])->first();

            if ($checkin) {
                $checkedIn[] = [
                    'checkin_id' => $checkin['id'],
                    'state' => $checkin['state'],
                    'created_at' => $checkin['created_at'],
                    'name' => $item['name'],
                    'email' => $item['name'],
                    'class' => $si['class'],
                    'number' => $si['number'],
                ];
            } else {
                $notCheckedIn[] = [
                    'checkin_id' => '',
                    'state' => 'NOT_CHECKED_IN',
                    'created_at' => '',
                    'name' => $item['name'],
                    'email' => $item['name'],
                    'class' => '',
                    'number' => '',
                ];
            }
        }
        $c = array_column($checkedIn, 'class');
        $n = array_column($checkedIn, 'number');
        array_multisort($c, SORT_ASC, $n, SORT_ASC, $checkedIn);

        return response()->json(array_merge($checkedIn, $notCheckedIn), Response::HTTP_OK);
    }

    public function checkin(Request $request, $courseUuid)
    {
        $course = $this->courseRepository->findCourseByUuid($courseUuid);
        if (!$course) {
            return response()->json(['error' => 'course_not_found'], Response::HTTP_NOT_FOUND);
        }
        $now = Carbon::now();
        $startTime = Carbon::createFromFormat('Y-m-d H:i:s', $course['start_timestamp']);
        $lateTime = Carbon::createFromFormat('Y-m-d H:i:s', $course['start_timestamp'])->add(CarbonInterval::createFromFormat('H:i:s', $course['late_time']));
        $expireTime = Carbon::createFromFormat('Y-m-d H:i:s', $course['start_timestamp'])->add(CarbonInterval::createFromFormat('H:i:s', $course['expire_time']));
        if ($now < $startTime || $now > $expireTime) {
            return response()->json(['error' => 'out_of_time'], Response::HTTP_BAD_REQUEST);
        }
        $user = Auth::user();
        if (!$this->courseRepository->hasCourseStudent($course['google_classroom_id'], $user['google_user_id'])) {
            return response()->json(['error' => 'student_out_of_course'], Response::HTTP_FORBIDDEN);
        }
        $state = $now < $lateTime ? 'ON_TIME' : 'LATE';
        $checkin = $this->checkinRepository->createCheckin([
            'course_id' => $course['id'],
            'student_id' => $user['id'],
            'state' => $state,
        ]);

        return response()->json($checkin, Response::HTTP_OK);
    }
}
