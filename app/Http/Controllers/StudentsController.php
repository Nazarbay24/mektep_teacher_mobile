<?php

namespace App\Http\Controllers;

use App\Repositories\StudentsRepository;
use App\Repositories\SubjectRepository;
use Illuminate\Http\Request;

class StudentsController extends Controller
{
    protected $repository;
    protected $subjectRepository;

    public function __construct(StudentsRepository $repository, SubjectRepository $subjectRepository)
    {
        $this->repository = $repository;
        $this->subjectRepository = $subjectRepository;
    }


    public function classList() {
        $teacher = auth()->user();

        $classList = $this->repository->classList($teacher);

        return response()->json($classList, 200);
    }


    public function studentsList($locale, $id_class) {
        $this->repository->init((int) auth()->user()->id_mektep);

        $studentsList = $this->repository->studentsList($id_class);

        return response()->json($studentsList, 200);
    }


    public function studentTabel($locale, $id_student) {
        $this->repository->init((int) auth()->user()->id_mektep);

        $studentTabel = $this->repository->studentTabel($id_student);

        return response()->json($studentTabel, 200);
    }


    public function criterialSubjectsByClass($locale, $id_class) {
        $this->subjectRepository->init((int) auth()->user()->id_mektep);

        $subjects = $this->subjectRepository->criterialSubjectsByClass($id_class);

        return response()->json($subjects, 200);
    }
}
