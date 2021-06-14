<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function createToken(Request $request, $org)
    {
        $id_token = $request->input('id_token');
        $access_token = $request->input('access_token');

        if (config('google.' . $org) == null) {
            return response()->json(['error' => 'org_not_found'], Response::HTTP_NOT_FOUND);
        }
        $client = new Google_Client();  // Specify the CLIENT_ID of the app that accesses the backend
        $client->setAuthConfig(config('google.' . $org . '.client_secret'));
        $payload = $client->verifyIdToken($id_token);
        if (!$payload) {
            return response()->json(['error' => 'Invalid ID token'], Response::HTTP_UNAUTHORIZED);
        }
        $GID = $payload['sub'];
        $domain = $payload['hd'] ?? 'gmail.com';
        $user = $this->userRepository->findUserByGoogleUserId($GID);
        if (!$user) {
            $user = $this->userRepository->createUser([
                'google_user_id' => $GID,
                'domain' => $domain,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'photo' => $payload['picture']]);
        }
        $this->userRepository->setUserAccessToken($user['id'], $access_token);
        $token = $user->createToken('api-token');

        return response()->json(['access_token' => $token->plainTextToken, 'token_type' => 'Bearer', 'exp' => $payload['exp'], 'user' => $user], Response::HTTP_CREATED);
    }

    public function whoami(Request $request)
    {
        $user = Auth::user();
        $studentInfo = $this->userRepository->getStudentInfo($user['id']);
        if ($studentInfo) {
            $user['student'] = $studentInfo->only(['class', 'number']);
        }

        return response()->json($user, Response::HTTP_OK);
    }

    public function setStudent(Request $request)
    {
        $class = $request->input('class');
        $number = $request->input('number');
        $user = Auth::user();
        $this->userRepository->setUserStudentInfo($user['id'], ['class' => $class, 'number' => $number]);

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function revokeToken(Request $request)
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
