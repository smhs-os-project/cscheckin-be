<?php

namespace App\Repositories;

use App\Entities\Checkin;
use App\Entities\StudentInformation;
use App\Entities\User;
use App\Entities\UserGoogleToken;

class CheckinRepository
{

    /**
     * @var Checkin
     */
    private $checkin;

    public function __construct(Checkin $checkin)
    {
        $this->checkin = $checkin;
    }

    public function allCheckin()
    {
        return $this->checkin->all();
    }

    public function findCheckinByCourseId($courseId)
    {
        return $this->checkin->where('course_id',$courseId)->get();
    }

    public function createCheckin($data)
    {
        return $this->checkin->create($data);
    }

}
