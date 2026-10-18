<?php

namespace App\Controllers;

use App\Models\Cohort;

class CohortController extends Controller
{
    public function getAllCohorts()
    {
        $cohorts = Cohort::getAll();
        $this->jsonResponse($cohorts);
    }
}
