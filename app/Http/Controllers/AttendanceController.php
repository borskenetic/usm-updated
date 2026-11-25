<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\SMSController;
use App\Models\AttendanceLog;
use App\Models\Student;
use App\Services\AttendanceSessionService;
use App\Models\Setting;
use App\Models\AdminActivity;
use App\Services\AdminActivityLogger;
use App\Support\PatronQrResolver;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function showScanner()
    {
        return view('attendance.scan', [
            'logoutFeedbackEnabled' => Setting::logoutFeedbackEnabled(),
        ]);
    }

    public function feedbackSettings()
    {
        return view('attendance.feedback_settings', [
            'enabled' => Setting::logoutFeedbackEnabled(),
        ]);
    }

    public function updateFeedbackSettings(Request $request)
    {
        $request->validate([
            'enabled' => 'required|in:0,1',
        ]);

        Setting::setLogoutFeedbackEnabled($request->input('enabled') === '1');

        AdminActivityLogger::staff(
            AdminActivity::TYPE_SETTINGS,
            'Attendance logout feedback '.($request->input('enabled') === '1' ? 'enabled' : 'disabled'),
            null,
            route('attendance.feedback.settings'),
            'staff',
        );

        return back()->with(
            'success',
            $request->input('enabled') === '1'
                ? 'Logout feedback is now enabled on the attendance scanner.'
                : 'Logout feedback is now disabled on the attendance scanner.'
        );
    }

    private function studentScanResponse(Student $student, AttendanceLog $log, string $status, bool $duplicate = false)
    {
        return response()->json([
            'type' => 'student',
            'student_id' => $student->id,
            'duplicate' => $duplicate,
            'student' => [
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'profile_picture' => $student->profile_picture,
            ],
            'status' => $status,
            'logout_feedback_enabled' => Setting::logoutFeedbackEnabled(),
            'log' => [
                'scanned_at' => Carbon::parse($log->scanned_at)->timezone('Asia/Manila')->format('Y-m-d h:i:s A'),
            ],
        ]);
    }

    public function scan(Request $request)
    {
        $request->validate(['qrcode' => 'required|string']);

        $resolved = app(PatronQrResolver::class)->resolve($request->qrcode);
        $student = $resolved['student'];

        if (! $student) {
            return response()->json([
                'type' => 'error',
                'message' => $resolved['reason'] ?? 'RFID not recognized.',
            ]);
        }

        $sessions = app(AttendanceSessionService::class);
        $sessions->closeStaleOpenInForStudent($student);

        // Same card held at the reader / double Enter → do not flip IN/OUT again.
        $recent = $sessions->recentScanWithin($student);
        if ($recent) {
            return $this->studentScanResponse(
                $student,
                $recent,
                strtoupper(trim((string) $recent->status)),
                true
            );
        }

        $lastLog = AttendanceLog::where('student_id', $student->id)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        $newStatus = ($lastLog && $sessions->isInStatus($lastLog->status)) ? 'OUT' : 'IN';

        $log = AttendanceLog::create([
            'student_id' => $student->id,
            'status' => $newStatus,
            'scanned_at' => Carbon::now('Asia/Manila'),
        ]);

        if (! empty($student->mobile_number)) {
            $template = Setting::where('key', 'scan_sms')->value('value')
                ?? 'Hello {name}, you scanned {status} at the library at {time}.';

            $message = str_replace(
                ['{name}', '{status}', '{time}'],
                [
                    trim($student->firstname.' '.$student->lastname),
                    $newStatus,
                    Carbon::now('Asia/Manila')->format('h:i A'),
                ],
                $template
            );

            app(SMSController::class)->sendDirect(
                $student->mobile_number,
                $message
            );
        }

        return $this->studentScanResponse($student, $log, $newStatus);
    }

    // Show the change video page
    public function showChangeVideo()
    {
        return view('attendance.change_video');
    }

    // Handle video upload
    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4|max:512000', // 500MB
        ]);

        $video = $request->file('video');
        $filename = 'area51_product_slideshow.mp4'; // overwrite existing
        $video->move(base_path('videos'), $filename);

        AdminActivityLogger::staff(
            AdminActivity::TYPE_SETTINGS,
            'Attendance video updated',
            $filename,
            route('attendance.changeVideo'),
            'staff',
        );

        return redirect()->route('attendance.changeVideo')->with('success', 'Video uploaded successfully!');
    }
}
