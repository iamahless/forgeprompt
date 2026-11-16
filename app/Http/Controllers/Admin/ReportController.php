<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\BuildSpendReport;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Show the operators what users were charged, what Pixelforge charged us, and the profit.
     */
    public function __invoke(BuildSpendReport $buildReport): Response
    {
        return Inertia::render('admin/Report', $buildReport->handle());
    }
}
