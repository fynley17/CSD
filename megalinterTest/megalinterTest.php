<?php

function make_report($student_id)
{
    $report = new ReportBuilder();
    return buildReport($report, $student_id);
}
