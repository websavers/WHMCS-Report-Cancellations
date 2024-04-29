<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata['title'] = "Churn during " . $currentyear;
$reportdata['description'] = "This report shows the churn rate of new vs. terminated products monthly and their revenues. It's based on the termination date for the product. It does not include domains or addons.";
$reportdata['yearspagination'] = true;

$currency = getCurrency(null, 1);

$reportdata['tableheadings'] = array(
    "Month",
    "Terminations",
    "Lost Revenue",
    "New Products",
    "New Revenue",
    "Product Difference",
    "Revenue Difference",
);

$reportvalues = array();
$term_results = Capsule::table('tblhosting')
    ->select(
        Capsule::raw("date_format(termination_date,'%m') as month"),
        Capsule::raw("date_format(termination_date,'%Y') as year"),
        Capsule::raw("COUNT(id) as num_terminations"),
        Capsule::raw("SUM(amount) as lostrevenue"),
    )
    ->where('termination_date', '>=', ($currentyear - 2) . '-01-01')
    ->groupBy(Capsule::raw("date_format(termination_date,'%M %Y')"))
    ->orderBy('termination_date', 'asc')
    ->get()
    ->all();
foreach ($term_results as $result) {
    $month = (int) $result->month;
    $year = (int) $result->year;
    $num_terminations = $result->num_terminations;
    $lostrevenue = $result->lostrevenue;

    $reportvalues[$year][$month] = [
        $num_terminations,
        $lostrevenue
    ];
}
$new_results = Capsule::table('tblhosting')
    ->select(
        Capsule::raw("date_format(regdate,'%m') as month"),
        Capsule::raw("date_format(regdate,'%Y') as year"),
        Capsule::raw("COUNT(id) as num_newproducts"),
        Capsule::raw("SUM(amount) as newrevenue"),
    )
    ->where('regdate', '>=', ($currentyear - 2) . '-01-01')
    ->groupBy(Capsule::raw("date_format(regdate,'%M %Y')"))
    ->orderBy('regdate', 'asc')
    ->get()
    ->all();
foreach ($new_results as $result) {
    $month = (int) $result->month;
    $year = (int) $result->year;
    $num_newproducts = $result->num_newproducts;
    $newrevenue = $result->newrevenue;

    //Add to existing array
    $reportvalues[$year][$month][] = $num_newproducts;
    $reportvalues[$year][$month][] = $newrevenue;
}

foreach ($months as $k => $monthName) {

    if ($monthName) {

        $num_terminations = $reportvalues[$currentyear][$k][0];
        $lostrevenue = $reportvalues[$currentyear][$k][1];
        $num_newproducts = $reportvalues[$currentyear][$k][2];
        $newrevenue = $reportvalues[$currentyear][$k][3];

        $product_difference = $num_newproducts - $num_terminations;
        $revenue_difference = $newrevenue - $lostrevenue;

        $reportdata['tablevalues'][] = array(
            $monthName . ' ' . $currentyear,
            $num_terminations,
            formatCurrency($lostrevenue),
            $num_newproducts,
            formatCurrency($newrevenue),
            $product_difference,
            formatCurrency($revenue_difference),
        );

        $overall_terminations += $num_terminations;
        $overall_newproducts += $num_newproducts;

        $overall_revenue_diff += ($newrevenue - $lostrevenue);

    }

}
$numeric_month = (int)date("m", strtotime($currentmonth));
$num_months = ($currentyear == date("Y", strtotime($currentmonth)))? $numeric_month : 12;
$average_monthly_terms = number_format($overall_terminations / $num_months);
$average_monthly_revenue_diff = formatCurrency($overall_revenue_diff / $num_months);

$reportdata['footertext'] = "<p align=\"center\"><strong>Terminations in period: $overall_terminations. New products in period: $overall_newproducts. Monthly Average Terminations: $average_monthly_terms. Monthly Average Revenue Difference: $average_monthly_revenue_diff</strong></p>";

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
                'v'=>$reportvalues[$currentyear-2][$i][2] - $reportvalues[$currentyear-2][$i][0],
                'f'=>$reportvalues[$currentyear-2][$i][2] - $reportvalues[$currentyear-2][$i][0],
            ),
            array(
                'v'=>$reportvalues[$currentyear-1][$i][2] - $reportvalues[$currentyear-1][$i][0],
                'f'=>$reportvalues[$currentyear-1][$i][2] - $reportvalues[$currentyear-1][$i][0],
            ),
            array(
                'v'=>$reportvalues[$currentyear][$i][2] - $reportvalues[$currentyear][$i][0],
                'f'=>$reportvalues[$currentyear][$i][2] - $reportvalues[$currentyear][$i][0],
            ),
        ),
    );
}

$args = array();
$args['colors'] = '#3070CF,#F9D88C,#cb4c30';
$args['chartarea'] = '80,20,90%,350';

$reportdata['headertext'] = $chart->drawChart('Column',$chartdata,$args,'400px');
