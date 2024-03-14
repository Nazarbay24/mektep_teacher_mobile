<?php

namespace App\Http\Controllers;

use App\Repositories\PlanRepository;
use App\Repositories\SubjectRepository;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    protected $subjectRepository;
    protected $planRepository;

    public function __construct(SubjectRepository $subjectRepository, PlanRepository $planRepository)
    {
        $this->subjectRepository = $subjectRepository;
        $this->planRepository = $planRepository;
    }


    public function subject($locale, $id) {
        $this->subjectRepository->init((int) auth()->user()->id_mektep);
        $this->planRepository->init((int) auth()->user()->id_mektep);

        $subject = $this->subjectRepository->getSubject($id);
        $plan = $this->planRepository->getPlansByPredmet($id);

        $data = [
            'subject' => $subject,
            'plan' => $plan
        ];

        return response()->json($data, 200);
    }


    public function mySubjects() {
        $this->subjectRepository->init((int) auth()->user()->id_mektep);

        $subjects = $this->subjectRepository->mySubjects();

        return response()->json($subjects, 200);
    }
}
