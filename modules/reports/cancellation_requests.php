<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata['title'] = "Monthly Cancellation Requests for " . $currentyear;
$reportdata['description'] = "This report shows the number of cancellation requests by month. Note: these are not the dates of cancellation but rather the date they were requested.";
$reportdata['yearspagination'] = true;

$currency = getCurrency(null, 1);

$reportdata['tableheadings'] = array(
    "Month",
    "Cancellation Requests",
);

$reportvalues = array();
$results = Capsule::table('tblcancelrequests')
    ->select(
        Capsule::raw("date_format(date,'%m') as month"),
        Capsule::raw("date_format(date,'%Y') as year"),
        Capsule::raw("COUNT(id) as num_cancellations"),
    )
    ->where('date', '>=', ($currentyear - 2) . '-01-01')
    ->groupBy(Capsule::raw("date_format(date,'%M %Y')"))
    ->orderBy('date', 'asc')
    ->get()
    ->all();
foreach ($results as $result) {
    $month = (int) $result->month;
    $year = (int) $result->year;
    $num_cancellations = $result->num_cancellations;

    $reportvalues[$year][$month] = [
        $num_cancellations
    ];
}

foreach ($months as $k => $monthName) {

    if ($monthName) {

        $num_cancellations = $reportvalues[$currentyear][$k][0];

        $reportdata['tablevalues'][] = array(
            $monthName . ' ' . $currentyear,
            $num_cancellations,
        );

        $overall_cancellations += $num_cancellations;

    }

}
$numeric_month = (int)date("m", strtotime($currentmonth));
$num_months = ($currentyear == date("Y", strtotime($currentmonth)))? $numeric_month : 12;
$average_monthly = number_format($overall_cancellations / $num_months);

$reportdata['footertext'] = "<p align=\"center\"><strong>Annual Cancellations: $overall_cancellations. Monthly Average: $average_monthly</strong></p>";

$chartdata['cols'][] = array('label'=>'Days Range','type'=>'string');
$chartdata['cols'][] = array('label'=>$currentyear-2,'type'=>'number');
$chartdata['cols'][] = array('label'=>$currentyear-1,'type'=>'number');
$chartdata['cols'][] = array('label'=>$currentyear,'type'=>'number');

for ($i = 1; $i <= 12; $i++) {
    $chartdata['rows'][] = array(
        'c'=>array(
            array(
                'v'=>$months[$i],
            ),
            array(
                'v'=>$reportvalues[$currentyear-2][$i][0],
                'f'=>$reportvalues[$currentyear-2][$i][0],
            ),
            array(
                'v'=>$reportvalues[$currentyear-1][$i][0],
                'f'=>$reportvalues[$currentyear-1][$i][0],
            ),
            array(
                'v'=>$reportvalues[$currentyear][$i][0],
                'f'=>$reportvalues[$currentyear][$i][0],
            ),
        ),
    );
}

$args = array();
$args['colors'] = '#3070CF,#F9D88C,#cb4c30';
$args['chartarea'] = '80,20,90%,350';

$reportdata['headertext'] = $chart->drawChart('Column',$chartdata,$args,'400px');
