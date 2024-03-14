<?php

namespace App\Repositories;

use App\Models\Message;
use App\Models\ParentModel;
use App\Models\Predmet;
use App\Models\Student;
use App\Models\Teacher;

class MessangerRepository
{
    protected $messageModel;
    protected $parentModel;
    protected $predmetModel;

    public function __construct(Message $messageModel, ParentModel $parentModel, Predmet $predmetModel)
    {
        $this->messageModel = $messageModel;
        $this->parentModel = $parentModel;
        $this->predmetModel = $predmetModel;
    }


    public function classList($teacher) {
        $classQuery = $this->predmetModel
            ->select('mektep_class.id as class_id',
                'mektep_class.class as class',
                'mektep_class.group as group',
                'mektep_class.edu_language as lang',
                'mektep_teacher.id as kurator_id',
                'mektep_teacher.surname as kurator_surname',
                'mektep_teacher.name as kurator_name')
            ->join('mektep_class', $this->predmetModel->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('mektep_teacher', 'mektep_class.kurator', '=', 'mektep_teacher.id')
            ->where($this->predmetModel->getTable().'.id_teacher', '=', $teacher->id)
            ->where($this->predmetModel->getTable().'.id_mektep', '=', $teacher->id_mektep)
            ->orderBy('class')
            ->orderBy('group')
            ->groupBy('class_id')
            ->get()->all();

        $messages = $this->messageModel
            ->select('mektep_students.id_class as id_class')
            ->join('mektep_students', $this->messageModel->getTable().'.child_id', '=', 'mektep_students.id')
            ->where($this->messageModel->getTable().'.poluchatel_id', '=', $teacher->id.'@t')
            ->where($this->messageModel->getTable().'.id_mektep', '=', $teacher->id_mektep)
            ->where($this->messageModel->getTable().'.read_status', '=', 0)
            ->orderBy($this->messageModel->getTable().'.date_server')
            ->get()->all();

        $classMessageCount = [];
        foreach ($messages as $item) {
            if (array_key_exists($item['id_class'], $classMessageCount)) {
                $classMessageCount[$item['id_class']]++;
            }
            else {
                $classMessageCount[$item['id_class']] = 1;
            }
        }

        $classList = [];
        foreach ($classQuery as $item) {
            $item['class'] = $item['class'].'«'.$item['group'].'»';
            unset($item['group']);

            $item['message_count'] = 0;
            if (array_key_exists($item['class_id'], $classMessageCount)) {
                $item['message_count'] = $classMessageCount[$item['class_id']];
                array_unshift($classList, $item);
            }
            else {
                $classList[] = $item;
            }
        }

        return $classList;
    }


    public function studentsList($id_class, $teacher) {
        $classInfo = $this->predmetModel
            ->select('mektep_class.id as class_id',
                'mektep_class.class as class',
                'mektep_class.group as group',
                'mektep_class.edu_language as lang',
                'mektep_teacher.id as kurator_id',
                'mektep_teacher.surname as kurator_surname',
                'mektep_teacher.name as kurator_name')
            ->join('mektep_class', $this->predmetModel->getTable().'.id_class', '=', 'mektep_class.id')
            ->leftJoin('mektep_teacher', 'mektep_class.kurator', '=', 'mektep_teacher.id')
            ->where($this->predmetModel->getTable().'.id_class', '=', $id_class)
            ->first();

        $classInfo['class'] = $classInfo['class'].'«'.$classInfo['group'].'»';
        unset($classInfo['group']);

        $students = Student::
            select('id',
                'name',
                'surname',
                'parent_ata_id',
                'parent_ana_id',)
            ->where('id_class', '=', $id_class)
            ->orderBy('surname')
            ->get()->all();

        $parentsIds = [];

        foreach ($students as $student) {
            if ($student['parent_ata_id'] > 0) $parentsIds[] = $student['parent_ata_id'];
            if ($student['parent_ana_id'] > 0) $parentsIds[] = $student['parent_ana_id'];
        }

        $parents = $this->parentModel
            ->select('id',
                    'name',
                    'surname',
                    'metka',
                    'last_visit')
            ->whereIn('id', $parentsIds)
            ->where('login', '!=', " ")
            ->where('status', '=', 1)
            ->where('blocked', '=', 0)
            ->get()->all();


        $messages = $this->messageModel
            ->select($this->messageModel->getTable().'.otpravitel_id')
            ->join('mektep_students', $this->messageModel->getTable().'.child_id', '=', 'mektep_students.id')
            ->where($this->messageModel->getTable().'.poluchatel_id', '=', $teacher->id.'@t')
            ->where($this->messageModel->getTable().'.id_mektep', '=', $teacher->id_mektep)
            ->where('mektep_students.id_class', '=', $id_class)
            ->where($this->messageModel->getTable().'.read_status', '=', 0)
            ->orderBy($this->messageModel->getTable().'.date_server')
            ->get()->all();

        $parentMessageCount = [];
        foreach ($messages as $item) {
            $id_parent = explode('@', $item['otpravitel_id'])[0];

            if (array_key_exists($id_parent, $parentMessageCount)) {
                $parentMessageCount[$id_parent]++;
            }
            else {
                $parentMessageCount[$id_parent] = 1;
            }
        }


        $parentsList = [];
        foreach ($students as $student) {
            $item = [];

            $ataKey = array_search($student['parent_ata_id'], array_column($parents, 'id'));
            $parentAta = $ataKey ? $parents[$ataKey] : null;

            $anaKey = array_search($student['parent_ana_id'], array_column($parents, 'id'));
            $parentAna = $anaKey ? $parents[$anaKey] : null;

            $item['student_id'] = $student['id'];
            $item['student_name'] = $student['surname'].' '.$student['name'];

            $item['ata'] = $parentAta ? [
                'id' => $parentAta['id'],
                'name' => $parentAta['surname'].' '.$parentAta['name']
            ] : null;

            $item['ana'] = $parentAna ? [
                'id' => $parentAna['id'],
                'name' => $parentAna['surname'].' '.$parentAna['name']
            ] : null;


            if ($parentAta || $parentAna) {
                $elem = [
                    'student_id' => $student['id'],
                    'student_name' => $student['surname'].' '.$student['name'],
                    'father' => $parentAta ? $item['ata'] = [
                        'id' => $parentAta['id'],
                        'name' => $parentAta['surname'].' '.$parentAta['name'],
                        'message_count' => array_key_exists($parentAta['id'], $parentMessageCount) ? $parentMessageCount[$parentAta['id']] : 0
                    ] : null,
                    'mother' => $parentAna ? [
                        'id' => $parentAna['id'],
                        'name' => $parentAna['surname'].' '.$parentAna['name'],
                        'message_count' => array_key_exists($parentAna['id'], $parentMessageCount) ? $parentMessageCount[$parentAna['id']] : 0
                    ] : null
                ];

                if (($parentAta && array_key_exists($parentAta['id'], $parentMessageCount)) || ($parentAna && array_key_exists($parentAna['id'], $parentMessageCount))) {
                    array_unshift($parentsList, $elem);
                }
                else {
                    $parentsList[] = $elem;
                }
            }
        }

        return $parentsList;
    }


    public function getMessages($id_parent, $id_student, $id_teacher) {
        $parent = $this->parentModel->findOrFail($id_parent);

        $messages = [];
        $messagesQuery = $this->messageModel
            ->where([
                ['poluchatel_id', '=', $id_teacher.'@t'],
                ['otpravitel_id', '=', $id_parent.'@p'],
            ])
            ->orWhere([
                ['poluchatel_id', '=', $id_parent.'@p'],
                ['otpravitel_id', '=', $id_teacher.'@t'],
            ])
            ->orderBy('date_server')
            ->get()->all();

        if ($messagesQuery) {
            $this->messageModel
                ->where([
                    ['poluchatel_id', '=', $id_teacher.'@t'],
                    ['otpravitel_id', '=', $id_parent.'@p'],
                ])
                ->orWhere([
                    ['poluchatel_id', '=', $id_parent.'@p'],
                    ['otpravitel_id', '=', $id_teacher.'@t'],
                ])
                ->orderBy('date_server')
                ->update(['read_status' => 1]);
        }

        foreach ($messagesQuery as $item) {
            $messages[] = [
                'id_message' => $item['id_mes'],
                'from' => $item['otpravitel_id'] == $id_teacher.'@t' ? 'teacher' : 'parent',
                'text' => $item['text'],
                'date' => date("d.m.y H:m", strtotime($item['date_server']))
            ];
        }


        if ($parent['last_visit'] == '0000-00-00 00:00:00') {
            $parent['last_visit'] = __('Никогда');
        }
        else {
            $parent['last_visit'] = date("d.m.y H:m", strtotime($parent['last_visit']));
        }

        return [
            'id_parent' => $parent['id'],
            'id_student' => intval($id_student),
            'parent_name' => $parent['surname'].' '.$parent['name'],
            'last_visit' => $parent['last_visit'],
            'messages' => $messages
        ];
    }


    public function addMessage($id_parent, $id_student, $text, $id_teacher, $id_mektep) {
        return $this->messageModel
            ->create([
                'otpravitel_id' => $id_teacher.'@t',
                'poluchatel_id' => $id_parent.'@p',
                'tema' => '',
                'text' => $text,
                'data_otpravki' => date('Y-m-d'),
                'date_server' => date("Y-m-d H:i:s"),
                'otpravitel_action' => 1,
                'poluchatel_action' => 1,
                'child_id' => $id_student,
                'id_mektep' => $id_mektep
            ]);
    }


    public function deleteMessage($id_message, $id_teacher) {
        return $this->messageModel
            ->where('id_mes', '=', $id_message)
            ->where('otpravitel_id', '=', $id_teacher.'@t')
            ->delete();
    }
}
