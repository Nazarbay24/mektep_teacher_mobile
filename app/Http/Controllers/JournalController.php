<?php

namespace App\Http\Controllers;

use App\Repositories\DiaryRepository;
use App\Repositories\JournalRepository;
use App\Repositories\PlanRepository;
use App\Services\Firebase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class JournalController extends Controller
{
    protected $repository;
    protected $planRepository;
    protected $diaryRepository;

    public function __construct(JournalRepository $repository, PlanRepository $planRepository, DiaryRepository $diaryRepository)
    {
        $this->repository = $repository;
        $this->planRepository = $planRepository;
        $this->diaryRepository = $diaryRepository;
    }


    public function journalView($locale, $id_predmet, Request $request) {
        $user = auth()->user();
        $this->repository->init((int) $user->id_mektep);
        $chetvert = $this->getChetvert($request);


        $journal = $this->repository->journalView($id_predmet, $user->id, $chetvert['chetvert'], $chetvert['isCurrentChetvert'], $chetvert['canMark']);

        return response()->json($journal, 200);
    }


    public function journalEdit($locale, $id_predmet, Request $request) {
        $user = auth()->user();
        $this->repository->init((int) $user->id_mektep);
        $this->planRepository->init((int) $user->id_mektep);
        $chetvert = $this->getChetvert($request);
        $date = $request->input('date');

        $journal = $this->repository->journalEdit($id_predmet, $user->id, $chetvert['chetvert'], $date, $chetvert['isCurrentChetvert'], $chetvert['canMark']);

        $journal['plan'] = [];
        if ($journal['tema_selected'] == false) {
            $journal['plan'] = $this->planRepository->getPlansByPredmet($id_predmet);
        }

        return response()->json($journal, 200);
    }


    public function setTema(Request $request) {
        $user = auth()->user();
        $this->planRepository->init((int) $user->id_mektep);
        $this->diaryRepository->init((int) $user->id_mektep);

        $id_predmet = $request->input('id_predmet');
        $id_plan = $request->input('id_plan');
        $date = $request->input('date');

        $plan = $this->planRepository->getPlan($id_plan);

        $diaryUpdated = $this->diaryRepository->setTema($user->id, $id_predmet, $plan, $date);

        if ($diaryUpdated) return response()->json(["message" => "success"], 200);
        else               return response()->json(["message" => "Not found"], 404);
    }


    public function setMark(Request $request) {
        $user = auth()->user();
        $this->repository->init((int) $user->id_mektep);
        $date = $request->input('date');
        $id_predmet = $request->input('id_predmet');
        $id_student = $request->input('id_student');
        $id_class = $request->input('id_class');
        $lessonNum = $request->input('lesson_num');
        $mark = $request->input('mark');

        $setMark = $this->repository->setMark($user->id, $date, $id_predmet, $id_student, $id_class, $lessonNum, $mark);

        if ($setMark) {
            $chetvert = $this->getChetvert($request, $date);
            $formativeMark = $this->repository->getFormative($id_student, $id_predmet, $chetvert['chetvert']);

            $mark = [
                'id' => (int)$id_student,
                'mark' => $setMark['jurnal_mark'],
            ];
            if ($formativeMark) $mark['formative_mark'] = strval($formativeMark);

            Firebase::sendGrade(1, $id_predmet, $id_student, $setMark['jurnal_mark'], $setMark['jurnal_date']);

            return response()->json($mark, 200);
        }
        else return response()->json(['message' => __('Не удалось выставить оценку')], 404);


    }


    public function getChetvert($request, $date = null) {
        $currentChetvert = 1;
        $chetvertDates = config('mektep_config.chetvert');
        foreach ($chetvertDates as $key => $item) {
            if ($date) {
                if($item['start'] <= $date) {
                    $currentChetvert = +$key;
                }
            }
            else {
                if($item['start'] <= date("Y-m-d")) { // заменить на текущую дату  date("Y-m-d", time())
                    $currentChetvert = +$key;
                }
            }
        }

        $isCurrentChetvert = false;
        if ($request->input('chetvert')) {
            $chetvert = $request->input('chetvert');
            if ($request->input('chetvert') == $currentChetvert) $isCurrentChetvert = true;
        }
        else {
            $chetvert = $currentChetvert;
            $isCurrentChetvert = true;
        }
        $canMark = $currentChetvert >= $chetvert ? true : false;

        return [
            'chetvert' => (int)$chetvert,
            'isCurrentChetvert' => $isCurrentChetvert,
            'canMark' => $canMark
        ];
    }
}
