<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidInputException;
use App\Repositories\UserRepository;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Create the token of the specified organization
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Google\Exception
     * @throws InvalidInputException
     */
    public function createToken(Request $request)
    {
        $idToken = $request->input('id_token');
        $accessToken = $request->input('access_token');
        $orgToken = config("clientId.common");

        if (!is_string($idToken) || !is_string($accessToken)) {
            throw new InvalidInputException("id_token 或 access_token 不是字串。", [
                "id_token" => $idToken,
                "access_token" => $accessToken,
            ]);
        }

        $client = new Google_Client();
        $client->setAuthConfig($orgToken->get("client_id"));
        $payload = $client->verifyIdToken($idToken);
        if (!$payload) {
            throw new InvalidInputException("id_token 的資料有誤。", [
                "id_token" => $idToken,
            ]);
        }

        $userId = $payload["sub"];
        $domain = $payload["hd"] ?? "gmail.com";
        $user = $this->userRepository->findUserByGoogleUserId($userId);

        if (!$user) {
            $user = $this->userRepository->createUser([
                'google_user_id' => $userId,
                'domain' => $domain,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'photo' => $payload['picture']
            ]);
        }


        $this->userRepository->setUserAccessToken($user['id'], $accessToken);
        $token = $user->createToken('api-token');
        $studentInfo = $this->userRepository->getStudentInfo($user['id']);
        if ($studentInfo) {
            $user['student'] = $studentInfo->only(['class', 'number']);
        }

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
