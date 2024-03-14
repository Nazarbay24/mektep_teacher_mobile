<?php

namespace App\Http\Controllers;


use App\Repositories\TeacherRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    protected $repository;

    public function __construct(TeacherRepository $repository)
    {
        $this->repository = $repository;
    }


    public function login(Request $request)
    {
        $request->validate([
            "iin" => "required|size:12",
            "password" => "required"
        ]);

        $userAccounts = $this->repository->login(trim($request->input('iin')), trim($request->input('password')), $request->ip());

        if($userAccounts) {
            $token = $userAccounts[0]->generateAuthToken();
            Redis::set('teacher_token:'.$userAccounts[0]->id, $token, 'EX', 60*60*24*30);

            return response()->json([
                'token' => $token,
                'name' => $userAccounts[0]->name,
                'surname' => $userAccounts[0]->surname,
                'lastname' => $userAccounts[0]->lastname,
                'pol' => $userAccounts[0]->pol,
            ], 200);
        }

        return response()->json(['message' => __('Неправильный ИИН или пароль')], 404);
    }


    public function getSchools()
    {
        $schools = $this->repository->getSchools(auth()->user()->iin);

        if ($schools) {
            return response()->json($schools, 200);
        }
        else {
            return response()->json(['message' => __('Школа не найдена')], 404);
        }
    }


    public function choiceSchool($loacle, $id, Request $request)
    {
        $user = $this->repository->choiceSchool($id, auth()->user()->iin, $request->ip());

        if ($user) {
            auth()->invalidate();
            $token = $user->generateAuthToken();
            Redis::set('teacher_token:'.$user->id, $token, 'EX', 60*60*24*30);

            return response()->json(['token' => $token], 200);
        }
        else return response()->json(['message' => __('Школа не найдена')], 404);
    }


    public function checkAuth(Request $request) {
        if( $request->input('device_info' ) == null  ) return response()->json(['message' => 'device_info cannot be null'], 400);

        $checkAndLog = $this->repository->teacherLog(auth()->user()->id, $request->ip(), $request->input('device_info'));

        if ($checkAndLog) return response()->json(['message' => 'OK'], 200);
        else              return response()->json(['message' => 'Token is Invalid'],401);
    }


    public function logout() {
        if (auth()->invalidate()) return response()->json(['message' => 'success'], 200);
        else                      return response()->json(['message' => 'Token is Invalid'], 401);
    }
}
