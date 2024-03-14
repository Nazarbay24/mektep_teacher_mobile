<?php

namespace App\Http\Controllers;

use App\Repositories\NewsRepository;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    protected $repository;

    public function __construct(NewsRepository $repository)
    {
        $this->repository = $repository;
    }


    public function newsList(Request $request) {
        $news = $this->repository->newsList();
        $newsList = [];

        foreach ($news as $item) {
            if ($item['image_url'] == '') {
                $item['image_url'] = 'https://mobile.mektep.edu.kz/uploads/images/default_background.jpg';
            }

            $strTime = strtotime($item['datetime']);
            $dayOfMonth = date('d', $strTime);
            $month = date('m', $strTime);
            $year = date('Y', $strTime);
            $time = date('H:i', $strTime);

            $item['date'] = ltrim($dayOfMonth, 0).' '.__('m_'.$month).' '.$year.' '.$time;
            unset($item['datetime']);

            $newsList[] = $item;
        }

        return response()->json($newsList, 200);
    }


    public function getNew($locale, $id_new) {
        $new = $this->repository->getNewById($id_new);

        if ($new['image_url'] == '') {
            $new['image_url'] = 'https://mobile.mektep.edu.kz/uploads/images/default_background.jpg';
        }

        $strTime = strtotime($new['datetime']);
        $dayOfMonth = date('d', $strTime);
        $month = date('m', $strTime);
        $year = date('Y', $strTime);
        $time = date('H:i', $strTime);

        $new['date'] = ltrim($dayOfMonth, 0).' '.__('m_'.$month).' '.$year.' '.$time;
        unset($new['datetime']);

        return response()->json($new, 200);
    }
}
