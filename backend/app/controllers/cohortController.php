<?php

namespace App\Controllers;

use App\Models\Cohort;

class CohortController extends Controller
{
    public function getAllCohorts()
    {
        // Supply the cohort options displayed in the registration form.
        $cohorts = Cohort::getAll();
        $this->jsonResponse($cohorts);
    }
}
