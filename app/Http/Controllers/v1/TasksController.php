<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Role;
use App\User;
use App\Task;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination;
use App\Log;
use App\Notification;
class TasksController extends Controller {
    /*
     * Add Task
     * Edit Task
     * Delete Task
     */
    public function getTasks(){
        $tasks = DB::table('tasks')->paginate(10);

        return $this->returnSuccess($tasks);
    }

    public function addTask(Request $request,JwtToken $jwtToken){
        try {
            $rules = [
                'name' => 'required|min:2',
                'description' =>'required|min:8',
                'status' => 'required',
                'assign' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }
            $task = new Task();

            $user = $this->getUserLog();

            $task->name = $request->name;
            $task->description = $request->description;
            $task->status = $request->status;
            $task->assign = $request->assign;
            $task->user_id = $user->id;

            $notification = new Notification();
            $notification->user_id = $task->assign;
            $notification->message = $user->name . ' has assigned you a task';
            $notification->save();

            $task->save();

            return $this->returnSuccess($task);
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    public function editTask($id,Request $request){
        try {
            $task = Task::find($id);
            $user = $this->getUserLog();
            if($task->user_id !== $user->id){
                return $this->returnBadRequest("You can edit just own task");
            }

            $status = '';
            switch ($task->status){
                case 0 : $status = 'Incomplete' ; break;
                case 1 : $status = 'Progress' ; break;
                case 2 : $status = 'Finished'; break;
            }

            $log = new Log([
                'task_id' => $id,
                'user_id' => $task->user_id,
                'type' => $user->role_id,
                'old_value' => 'Status : ' . $status . ' . Assign to ' . $task->assign
                ]);

            if($request->has('name'))
            {
                $task->name= $request->name;
            }

            if($request->has('description')){
                $task->description = $request->description;
            }

            if($request->has('status')){
                $task->status = $request->status;
            }

            if($request->has('assign')){
                $task->assign= $request->assign;

                $notification = new Notification();
                $notification->user_id = $task->assign;
                $notification->message = $user->name . ' has assigned you a task';
                $notification->save();
            }
            $status = '';
            switch ($task->status){
                case 0 : $status = 'Incomplete' ; break;
                case 1 : $status = 'Progress' ; break;
                case 2 : $status = 'Finished'; break;
            }
            $log->new_value= 'Status : ' . $status . ' . Assign to ' . $task->assign;
            $log->save();

            if($task->save()) {
                return $this->returnSuccess($task);
            }


            return $this->returnError('Error to save');

        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }
    public function deleteTask($id,Request $request){
        try {
            $task = Task::find($id);

            $task->delete();

            return $this->returnSuccess();
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    public function get()
    {
        try {
            $user = $this->validateSession();

            return $this->returnSuccess($user);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }


}