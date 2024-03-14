<?php

namespace App\Repositories;

use App\Models\Plan as Model;
use Illuminate\Notifications\Notifiable;

class PlanRepository
{
    use Notifiable;

    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function init(int $id_mektep)
    {
        $this->model->init($id_mektep);
    }

    public function getPlan($id_plan) {
        return $this->model->findOrFail($id_plan);
    }


    public function getPlansByPredmet($predmetId) {
        $plan = $this->model
            ->select('id','title', 'sagat')
            ->where('mektep_predmet_id', '=', $predmetId)
            ->where('teacher_id', '=', auth()->user()->id)
            ->orderBy('id', 'asc')
            ->get()->all();

        foreach ($plan as $key => $item) {
            $plan[$key]['sagat'] = $item['sagat'].' '.__('ч.');
            $plan[$key]['title'] = str_replace("\r\n",'', $item['title']);
        }

        return $plan;
    }

}
