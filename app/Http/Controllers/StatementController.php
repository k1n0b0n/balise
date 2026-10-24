<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Statements;
use Illuminate\Contracts\View\View;

/**
 * The accessibility and ecodesign statements (E1.5), rendered by the server from the self-audits.
 */
final class StatementController extends Controller
{
    public function accessibility(Statements $statements): View
    {
        return view('statements.accessibility', ['statement' => $statements->accessibility()]);
    }

    public function ecodesign(Statements $statements): View
    {
        return view('statements.ecodesign', ['statement' => $statements->ecodesign()]);
    }
}
