<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Stats\ActivityStream;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /admin/farm/activity: what happened on the web, newest first (ActivityStream, Roman 28 Nov 2026). */
class ActivityController extends Controller
{
    public function __invoke(Request $request, ActivityStream $stream): View
    {
        $window = array_key_exists((string) $request->query('window'), ActivityStream::WINDOWS) ? (string) $request->query('window') : '24h';
        $group = array_key_exists((string) $request->query('group'), ActivityStream::GROUPS) ? (string) $request->query('group') : null;
        $detailed = $request->boolean('detail');
        $session = $request->integer('session') ?: null;

        return view('admin.farm.activity', [
            'lines' => $stream->lines(ActivityStream::WINDOWS[$window], $group, $detailed, $session),
            'window' => $window,
            'group' => $group,
            'detailed' => $detailed,
            'session' => $session,
            'query' => array_filter(['window' => $window === '24h' ? null : $window, 'group' => $group, 'detail' => $detailed ? 1 : null, 'session' => $session]),
        ]);
    }
}
