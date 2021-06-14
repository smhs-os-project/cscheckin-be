<?php

namespace App\Repositories;

use App\Entities\Course;
use App\Entities\CourseStudent;
use App\Entities\StudentInformation;
use App\Entities\User;
use App\Entities\UserGoogleToken;

class CourseRepository
{

    /**
     * @var Course
     */
    private $course;
    /**
     * @var CourseStudent
     */
    private $courseStudent;

    public function __construct(Course $course, CourseStudent $courseStudent)
    {
        $this->course = $course;
        $this->courseStudent = $courseStudent;
    }

    public function allCourse()
    {
        return $this->course->all();
    }

    public function findCourseById($id)
    {
        return $this->course->find($id);
    }

    public function findCourseByGoogleClassroomId($googleClassroomId)
    {
        return $this->course->where('google_classroom_id', $googleClassroomId)->get();
    }

    public function findCourseByTeacherId($teacherId)
    {
        return $this->course->where('teacher_id', $teacherId)->get();
    }

    public function getStudentByGoogleClassroomId($googleClassroomId)
    {
        return $this->courseStudent->where('google_classroom_id', $googleClassroomId)->get();
    }

    public function createCourse($data)
    {
        return $this->course->create($data);
    }

    public function setStudentByGoogleClassroomId($googleClassroomId, $students)
    {
        $this->courseStudent->where('google_classroom_id', $googleClassroomId)->delete();
        foreach ($students as $student) {
            $this->courseStudent->create($student);
        }
    }

    public function updateCourse($id, $data)
    {
        return $this->courseStudent->where('id', $id)->update($data);
    }

    public function deleteCourse($id)
    {
        return $this->courseStudent->find($id)->delete();
    }
}
