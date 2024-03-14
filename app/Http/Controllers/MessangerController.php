<?php

namespace App\Http\Controllers;

use App\Models\ParentModel;
use App\Repositories\MessangerRepository;
use App\Services\Firebase;
use Illuminate\Http\Request;

class MessangerController extends Controller
{
    protected $repository;

    public function __construct(MessangerRepository $repository)
    {
        $this->repository = $repository;
    }


    public function classList() {
        $teacher = auth()->user();

        $classList = $this->repository->classList($teacher);

        return response()->json($classList, 200);
    }


    public function studentsList($locale, $id_class) {
        $teacher = auth()->user();

        $studentsList = $this->repository->studentsList($id_class, $teacher);

        return response()->json($studentsList, 200);
    }


    public function getMessages(Request $request) {
        $teacher = auth()->user();
        $id_parent = $request->input('id_parent');
        $id_student = $request->input('id_student');

        $messages = $this->repository->getMessages($id_parent, $id_student, $teacher->id);

        return response()->json($messages, 200);
    }


    public function addMessage(Request $request) {
        $teacher = auth()->user();
        $id_parent = $request->input('id_parent');
        $id_student = $request->input('id_student');
        $text = $request->input('text');

        $addedMessage = $this->repository->addMessage($id_parent, $id_student, $text, $teacher->id, $teacher->id_mektep);


        if ($addedMessage) {
            $parent = ParentModel::select('firebase_token')
            ->where('id', $id_parent)
            ->whereNotNull('firebase_token')
            ->first();

            if ($parent) {
                Firebase::sendMessage(
                    $parent->firebase_token,
                    $text,
                    $teacher,
                    $id_parent,
                    $id_student
                );
            }

            return response()->json([
                'id_message' => $addedMessage['id_mes'],
                'from' => 'teacher',
                'text' => $addedMessage['text'],
                'date' => $addedMessage['date_server']
            ], 200);
        }
        else               return response()->json(['message' => 'Не удалось отправить сообщение'], 404);
    }


    public function deleteMessage(Request $request)
    {
        $teacher = auth()->user();
        $id_message = $request->input('id_message');

        $deletedMessage = $this->repository->deleteMessage($id_message, $teacher->id);

        if ($deletedMessage) return response()->json(['message' => 'Сообщение удалено'], 200);
        else                 return response()->json(['message' => 'Не удалось удалить сообщение'], 404);
    }
}
