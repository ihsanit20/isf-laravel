<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsMemberUnitCalendar;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberUnitCalendarController extends Controller
{
    use BuildsMemberUnitCalendar;

    public function show(Request $request, Member $member): Response
    {
        return Inertia::render('admin/members/UnitCalendar', [
            ...$this->buildMemberUnitCalendar($member, $request->integer('year') ?: null),
            'backUrl' => route('admin.members.index'),
            'backLabel' => 'Back to Member List',
            'calendarBaseUrl' => route('admin.members.unit-calendar', $member),
            'isAdminView' => true,
        ]);
    }
}
