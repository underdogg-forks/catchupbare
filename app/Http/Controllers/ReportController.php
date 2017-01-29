<?php namespace App\Http\Controllers;

use Auth;
use Config;
use Input;
use Utils;
use DB;
use Session;
use Str;
use View;
use App\Models\Company;

/**
 * Class ReportController
 */
class ReportController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function d3()
    {
        $message = '';
        $fileName = storage_path().'/dataviz_sample.txt';

        if (Auth::user()->company->hasFeature(FEATURE_REPORTS)) {
            $company = Company::where('id', '=', Auth::user()->company->id)
                            ->with(['relations.invoices.invoice_items', 'relations.contacts'])
                            ->first();
            $company = $company->hideFieldsForViz();
            $relations = $company->relations->toJson();
        } elseif (file_exists($fileName)) {
            $relations = file_get_contents($fileName);
            $message = trans('texts.sample_data');
        } else {
            $relations = '[]';
        }

        $data = [
            'relations' => $relations,
            'message' => $message,
        ];

        return View::make('reports.d3', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showReports()
    {
        $action = Input::get('action');

        if (Input::get('report_type')) {
            $reportType = Input::get('report_type');
            $dateField = Input::get('date_field');
            $startDate = date_create(Input::get('start_date'));
            $endDate = date_create(Input::get('end_date'));
        } else {
            $reportType = ENTITY_INVOICE;
            $dateField = FILTER_INVOICE_DATE;
            $startDate = Utils::today(false)->modify('-3 month');
            $endDate = Utils::today(false);
        }

        $reportTypes = [
            'relation',
            'product',
            'invoice',
            'invoice_details',
            'aging',
            'profit_and_loss',
            'payment',
            'expense',
            'task',
            'tax_rate',
        ];

        $params = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'reportTypes' => array_combine($reportTypes, Utils::trans($reportTypes)),
            'reportType' => $reportType,
            'title' => trans('texts.charts_and_reports'),
            'company' => Auth::user()->company,
        ];

        if (Auth::user()->company->hasFeature(FEATURE_REPORTS)) {
            $isExport = $action == 'export';
            $reportClass = '\\App\\Ninja\\Reports\\' . Str::studly($reportType) . 'Report';
            $options = [
                'date_field' => $dateField,
                'invoice_status' => request()->invoice_status,
                'group_dates_by' => request()->group_dates_by,
            ];
            $report = new $reportClass($startDate, $endDate, $isExport, $options);
            if (Input::get('report_type')) {
                $report->run();
            }
            $params['report'] = $report;
            $params = array_merge($params, $report->results());
            if ($isExport) {
                self::export($reportType, $params['displayData'], $params['columns'], $params['reportTotals']);
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [];
            $params['report'] = false;
        }

        return View::make('reports.chart_builder', $params);
    }

    /**
     * @param $reportType
     * @param $data
     * @param $columns
     * @param $totals
     */
    private function export($reportType, $data, $columns, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        $reportType = trans("texts.{$reportType}s");
        $date = date('Y-m-d');

        header('Content-Type:application/csv');
        header("Content-Disposition:attachment;filename={$date}_Ninja_{$reportType}.csv");

        Utils::exportData($output, $data, Utils::trans($columns));

        /*
        fwrite($output, trans('texts.totals'));
        foreach ($totals as $currencyId => $fields) {
            foreach ($fields as $key => $value) {
                fwrite($output, ',' . trans("texts.{$key}"));
            }
            fwrite($output, "\n");
            break;
        }

        foreach ($totals as $currencyId => $fields) {
            $csv = Utils::getFromCache($currencyId, 'currencies')->name . ',';
            foreach ($fields as $key => $value) {
                $csv .= '"' . Utils::formatMoney($value, $currencyId).'",';
            }
            fwrite($output, $csv."\n");
        }
        */

        fclose($output);
        exit;
    }
}
