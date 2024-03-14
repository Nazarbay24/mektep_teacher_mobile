<?php

namespace App\Http\Controllers;


use App\Repositories\StatisticsRepository;

class StatisticsController extends Controller
{
    protected $repository;

    public function __construct(StatisticsRepository $repository)
    {
        $this->repository = $repository;
    }


    public function getStatistics() {
        $statistics = $this->repository->getStatistics();

        return response()->json($statistics, 200);
    }
}
