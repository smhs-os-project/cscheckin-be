<?php

namespace App\Repositories;

use App\Entities\StudentInformation;
use App\Entities\User;
use App\Entities\UserGoogleToken;

class UserRepository
{

    /**
     * @var User
     */
    private $user;
    /**
     * @var UserGoogleToken
     */
    private $userGoogleToken;
    /**
     * @var StudentInformation
     */
    private $studentInformation;

    public function __construct(User $user, UserGoogleToken $userGoogleToken, StudentInformation $studentInformation)
    {
        $this->user = $user;
        $this->userGoogleToken = $userGoogleToken;
        $this->studentInformation = $studentInformation;
    }

    public function allUser()
    {
        return $this->user->all();
    }

    public function findUserById($id)
    {
        return $this->user->find($id);
    }

    public function findUserByGoogleUserId($googleUserId)
    {
        return $this->user->where('google_user_id', $googleUserId)->first();
    }

    public function findUserByDomain($domain)
    {
        return $this->user->where('domain', $domain)->get();
    }

    public function findUserByEmail($email)
    {
        return $this->user->where('email', $email)->first();
    }

    public function getUserAccessToken($userId)
    {
        return $this->userGoogleToken->where('user_id', $userId)->first()->only('access_token');
    }

    public function getStudentInfo($userId)
    {
        return $this->studentInformation->where('user_id', $userId)->first();
    }

    public function createUser($data)
    {
        return $this->user->create($data);
    }

    public function setUserAccessToken($userId, $accessToken)
    {
        return $this->userGoogleToken->updateOrCreate(['user_id' => $userId], ['access_token' => $accessToken]);
    }

    public function setUserStudentInfo($userId, $studentInfo)
    {
        return $this->studentInformation->updateOrCreate(['user_id' => $userId], $studentInfo);
    }

    public function updateUser($id, $data)
    {
        return $this->user->where('id', $id)->update($data);
    }

    public function deleteUser($id)
    {
        return $this->user->find($id)->delete();
    }
}
