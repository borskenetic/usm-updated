<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ComposesLibraryIdCard;
use App\Models\Student;
use ZipArchive;

class IdCardController extends Controller
{
    use ComposesLibraryIdCard;

    public function front($id)
    {
        $student = Student::findOrFail($id);
        $img = $this->idCardTemplate('front');

        $this->composeIdCardFront($img, [
            'photo' => $student->profile_picture,
            'full_name' => $this->formatIdCardName(
                $student->firstname,
                $student->lastname,
                $student->middle_initial
            ),
            'subtitle' => $student->course,
            'id_number' => $student->id_number,
        ]);

        return $img->response('png');
    }

    public function back($id)
    {
        $student = Student::findOrFail($id);
        $img = $this->idCardTemplate('back');

        $this->composeIdCardBack($img, [
            'qrcode' => $student->qrcode ?: $student->id_number ?: ('S-'.$student->id),
            'signature' => $student->student_signature,
            'emergency_person' => $student->emergency_person,
            'emergency_address' => $student->emergency_address ?: $student->address,
            'emergency_number' => $student->emergency_number,
            'birth_date' => $student->birthday,
            'valid_until' => config('idcard.valid_until'),
        ]);

        return $img->response('png');
    }

    public function download($id)
    {
        $student = Student::findOrFail($id);

        $front = $this->front($id)->getContent();
        $back = $this->back($id)->getContent();

        $zipPath = storage_path("app/temp_id_{$id}.zip");
        $frontPath = storage_path("app/front_{$id}.png");
        $backPath = storage_path("app/back_{$id}.png");

        file_put_contents($frontPath, $front);
        file_put_contents($backPath, $back);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($frontPath, "{$student->lastname}_{$student->firstname}_front.png");
            $zip->addFile($backPath, "{$student->lastname}_{$student->firstname}_back.png");
            $zip->close();
        }

        unlink($frontPath);
        unlink($backPath);

        return response()->download($zipPath, "{$student->lastname}_{$student->firstname}_ID.zip")
            ->deleteFileAfterSend(true);
    }
}
