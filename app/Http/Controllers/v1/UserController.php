<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Role;
use App\User;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\LinkReset;
use App\Task;
use App\Comment;
/**
 * Class UserController
 *
 * @package App\Http\Controllers\v1
 */
class UserController extends Controller
{
    /**
     * Login User
     *
     * @param Request $request
     * @param User $userModel
     * @param JwtToken $jwtToken
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request, User $userModel, JwtToken $jwtToken)
    {
        try {
            $rules = [
                'email' => 'required|email',
                'password' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            $user = $userModel->login($request->email, $request->password);

            if (!$user) {
                return $this->returnNotFound('Invalid credentials');
            }

            if ($user->status === User::STATUS_INACTIVE) {
                return $this->returnError('User is not approved by admin');
            }

            $token = $jwtToken->createToken($user);

            $data = [
                'user' => $user,
                'token' => $token->token()
            ];

            return $this->returnSuccess($data);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Register user
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $rules = [
                'name' => 'required',
                'email' => 'required|email|unique:users',
                'password' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            $user = new User();

            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->status = User::STATUS_INACTIVE;
            $user->role_id = Role::ROLE_USER;

            $user->save();

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Forgot password
     *
     * @param Request $request
     * @param User $userModel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request, User $userModel)
    {
        try {
            $rules = [
                'email' => 'required|email|exists:users'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            $user = $userModel::where('email', $request->email)->get()->first();

            $user->forgot_code = strtoupper(str_random(6));
            $user->save();

            //TODO should sent an email to user with code
            Mail::to($user)->send(new LinkReset($user));

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Change user password
     *
     * @param Request $request
     * @param User $userModel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request, User $userModel)
    {
        try {
            $rules = [
                'email' => 'required|email|exists:users',
                'code' => 'required',
                'password' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            $user = $userModel::where('email', $request->email)->where('forgot_code', $request->code)->get()->first();

            if (!$user) {
                $this->returnNotFound('Code is not valid');
            }

            $user->password = Hash::make($request->password);
            $user->forgot_code = '';

            $user->save();

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Get logged user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function get()
    {
        try {
            $user = $this->validateSession();

            return $this->returnSuccess($user);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Update logged user
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $user = $this->validateSession();

            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return $this->returnSuccess($user);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    public function addComment($id,Request $request) {
        $rules = [
            'comment'     => 'required|min:1'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ( ! $validator->passes()) {
            return $this->returnBadRequest();
        }
        $task = Task::find($id);

        $comment = new Comment([
            'user_id' => $task->user_id,
            'task_id' => $id,
            'comment' =>$request->comment
        ]);

        if($comment->save())
        {
            return $this->returnSuccess($comment);
        }

        return $this->returnError('An error occured');

    }
    public function editComment($id,Request $request) {
        $rules = [
            'comment'     => 'required|min:1'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ( ! $validator->passes()) {
            return $this->returnBadRequest();
        }

        $comment = Comment::find($id);

        $user = $this->getUserLog();

        if($comment->user_id !== $user->id && $user->role_id === 2) {
            return $this->returnError('You cannot edit this comment');
        }

        $comment->comment = $request->comment;

        if($comment->save()) {
            return $this->returnSuccess($comment);
        }

        return $this->returnError('An error occured');

    }
    public function deleteComment($id,Request $request) {
        $comment = Comment::find($id);

        if(!$comment) {
            return $this->returnNotFound('Comment not found');
        }

        if($comment->delete()){
            return $this->returnSuccess($comment);
        }

        return $this->returnError('An error occured');
    }
}