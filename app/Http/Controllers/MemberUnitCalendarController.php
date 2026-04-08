<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsMemberUnitCalendar;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberUnitCalendarController extends Controller
{
    use BuildsMemberUnitCalendar;

    public function show(Request $request, Member $member): Response
    {
        abort_unless($member->managed_by_user_id === $request->user()?->id, 403);

        return Inertia::render('members/UnitCalendar', [
            ...$this->buildMemberUnitCalendar($member, $request->integer('year') ?: null),
            'backUrl' => route('members.index'),
            'backLabel' => 'Back to My Membership',
            'calendarBaseUrl' => route('members.unit-calendar', $member),
            'isAdminView' => false,
        ]);
    }
}
