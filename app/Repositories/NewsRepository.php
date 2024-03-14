<?php

namespace App\Repositories;

use App\Models\News;

class NewsRepository
{
    protected $model;

    public function __construct(News $model)
    {
        $this->model = $model;

        if (app()->getLocale() == 'ru')      $this->lang = 'rus';
        else if (app()->getLocale() == 'kk') $this->lang = 'kaz';
    }


    public function newsList()
    {
        $lang = 'rus';
        if      (app()->getLocale() == 'ru') $lang = 'rus';
        else if (app()->getLocale() == 'kk') $lang = 'kaz';

        $news = $this->model
            ->select('id', 'title', 'datetime', 'filename as image_url')
            ->where('lang', '=', $lang)
            ->orderBy('datetime','desc')
            ->get()->take(20);

        return $news;
    }

    public function getNewById($id_new)
    {
        $item = $this->model
            ->select('date', 'title', 'text', 'datetime', 'filename as image_url')
            ->where('id', '=', $id_new)
            ->first();

        if ($item) {
            $this->model->where('id', '=', $id_new)->increment('views');
        }
        return $item;
    }
}
