<?php

namespace App\Services;

use App\Models\ParentModel;
use App\Models\Predmet;
use App\Models\Student;

class Firebase
{
    protected $studentsRepository;
    protected const URL = "https://fcm.googleapis.com/fcm/send";
    protected const KEY = "AAAAuYbi9Ls:APA91bET5QRRZTKwFaNvj2VHhd7RUuZPIZfWmz3VYh_ScHAAaoArHbRK0F_nXPXe1fe5PzEpqYvIGGc-540R90fEX6vjxxuX8mQjWVVDph9LUEcJMMt3Wo2LaXLcQMgv0FbhLMKVjyZQ";

    protected const TITLE = [
        'kk' => ' баға алды',
        'ru' => ' получил(а) оценку',
        'en' => ' got a grade'
    ];
    protected const BODY = [
        'kk' => '«predmet_name» пәнінен «grade» бағасын алды ',
        'ru' => 'по предмету «predmet_name» выставлена оценка «grade» ',
        'en' => 'received «grade» in «predmet_name» '
    ];

    public static function sendGrade($type, $id_predmet, $id_student, $mark, $date) {
        $key = self::KEY;
        $url = self::URL;
        $title_text = self::TITLE;
        $body_text = self::BODY;

        $student = self::getParentTokens($id_student);
        $message_date = '('.date('d.m.Y', strtotime($date)).')';
        $predmet_name = Predmet::select(
            'edu_predmet_name.predmet_rus as ru',
            'edu_predmet_name.predmet_kaz as kk',
            'edu_predmet_name.predmet_eng as en',
        )
        ->leftJoin('edu_predmet_name', 'mektep_predmet.predmet', '=', 'edu_predmet_name.id')
        ->where(['mektep_predmet.id' => $id_predmet])
        ->first();

        if( !empty($student['parents']) && (date('h:m') >= '08:00' && date('h:m') <= '21:00') ) {
            $myCurl = curl_init();
            foreach($student['parents'] as $parent) {

                $lang = $parent['mobile_lang'];
                $token = $parent['firebase_token'];
                $title = $student['name'].$title_text[$lang];
                $body = str_replace(['predmet_name', 'grade'], [$predmet_name[$lang], $mark], $body_text[$lang]).$message_date;

                curl_setopt_array($myCurl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: key=' . $key,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        "to" => $token,
                        "notification" => [
                            'title' => $title,
                            'body' => $body,
                            'sound' => true
                        ],
                        "data" => [
                            'type' => 1,
                            'child_id' => $id_student,
                            'predmet_id' => $id_predmet,
                            'date' => $date
                        ]
                    ])
                ));
                curl_exec($myCurl);
            }
            curl_close($myCurl);
        }
    }

    public static function sendMessage($token, $text, $teacher, $parent_id, $student_id) {
        $key = self::KEY;
        $url = self::URL;

        if(strlen($text) > 115) {
            $text = mb_substr($text, 0, 115). '...';
        }

        $myCurl = curl_init();
        curl_setopt_array($myCurl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . $key,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "to" => $token,
                "notification" => [
                    'title' => $teacher->surname.' '. $teacher->name,
                    'body' => $text,
                    'sound' => true
                ],
                "data" => [
                    'type' => 4,
                    'child_id' => $student_id,
                    'parent_id' => $parent_id,
                    'teacher_id' => $teacher->id,
                    'last_visit' => date('Y-m-d H:i:s')
                ]
            ])
        ));
        curl_exec($myCurl);
        curl_close($myCurl);
    }

    public static function getParentTokens($id_student) {
        $student = Student::select('id', 'name', 'parent_ata_id', 'parent_ana_id')
            ->where('id', '=', $id_student)
            ->first();

        $parents_ids = [];
        if ($student['parent_ata_id']) $parents_ids[] = $student['parent_ata_id'];
        if ($student['parent_ana_id']) $parents_ids[] = $student['parent_ana_id'];

        if(count($parents_ids) > 0) {
            $parents = ParentModel::select('id', 'firebase_token', 'mobile_lang')
                ->whereIn('id', $parents_ids)
                ->whereNotNull('firebase_token')
                ->get()->all();

            $student['parents'] = $parents;

            return $student;
        }
        else {
            return false;
        }
    }
}
