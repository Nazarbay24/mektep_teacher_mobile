<?php

namespace App\Http\Middleware;

use App\Models\Teacher;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use JWTAuth;

use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;


class JwtMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        }
        catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json(['message' => 'Token is Invalid'], 401);
            }

            else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                $user_id = JWTAuth::getJWTProvider()->decode($request->bearerToken())['sub'];
                $token = Redis::get('teacher_token:' . $user_id);

                if ($request->bearerToken() != $token) {
                    return response()->json(['message' => 'Token is Expired'], 401);
                }

                if ($new_token = auth()->refresh()) {
                    Redis::set('teacher_token:' . $user_id, $new_token, 'EX', 60 * 60 * 24 * 30);
                    $teacherCheckAndUpdate = Teacher::where('id', $user_id)
                        ->where('status', 1)
                        ->where('blocked', 0)
                        ->update(['device' => 'mobile', 'last_visit' => date('Y-m-d H:i:s')]);

                    if ($teacherCheckAndUpdate > 0) return response()->json(['token' => $new_token], 402);
                    else                            return response()->json(['message' => 'Token is Expired'], 401);
                }

                return response()->json(['message' => 'Token is Expired'], 401);
            }

            else{
                return response()->json(['message' => 'Authorization Token not found'], 401);
            }
        }
        return $next($request);
    }

    protected function checkIfUserHasToken()
    {
        return request()->headers->has('Authorization');
    }
}
