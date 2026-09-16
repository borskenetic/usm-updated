<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Registration</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-weight: 600}
        .card { border-radius: 12px; }
        .hidden { display: none; }
        canvas {
            touch-action: none;
            border: 1px solid #ccc;
            width: 100%;
            max-width: 500px;
            height: 150px;
            border-radius: 6px;
            background-color: #fff;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card mx-auto shadow-sm" style="max-width: 720px;">
        <div class="card-body">
            <h3 class="text-center mb-4">Library Registration</h3>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="text-center mb-4">
                <button type="button" class="btn btn-primary me-2" id="btnStudent">Student</button>
                <button type="button" class="btn btn-outline-success" id="btnEmployee">Faculty &amp; Staff</button>
            </div>

            {{-- STUDENT FORM --}}
            <form id="studentForm" method="POST" action="{{ route('pending.store') }}" enctype="multipart/form-data">
                @csrf
                <h5 class="mb-3">Student Information</h5>
            
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="firstname" class="form-control" placeholder="First Name"  >
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="lastname" class="form-control" placeholder="Last Name"  >
                    </div>
            
                    <div class="col-md-6">
                        <input type="text" name="middle_initial" class="form-control" placeholder="Middle Initial">
                    </div>
            
                    <div class="col-md-6">
                        <input type="text" name="id_number" class="form-control" placeholder="ID Number"  >
                    </div>
            
                    <div class="col-md-6">
                        <input type="date" name="birthday" class="form-control" placeholder="Birthday">
                    </div>
            
                    <div class="col-md-6">
                        <select name="course" class="form-select"  >
                            <option value="">Select Course</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->program_code }}"
                                    {{ old('course') == $program->program_code ? 'selected' : '' }}>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
            
                    <div class="col-md-6">
                        <select name="year" class="form-select"  >
                            <option value="">Select Year</option>
                            @foreach(['1st','2nd','3rd','4th','5th'] as $y)
                                <option value="{{ $y }} Year">{{ $y }} Year</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="mobile_number" class="form-control" placeholder="Mobile Number"
                               value="{{ old('mobile_number') }}">
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="address" class="form-control" placeholder="Address"
                               value="{{ old('address') }}">
                    </div>

                    <div class="col-12 mt-2">
                        <h6 class="mb-2">Emergency Contact</h6>
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="emergency_person" class="form-control" placeholder="Emergency Person"
                               value="{{ old('emergency_person') }}">
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="emergency_relationship" class="form-control" placeholder="Relationship"
                               value="{{ old('emergency_relationship') }}">
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="emergency_number" class="form-control" placeholder="Emergency Number"
                               value="{{ old('emergency_number') }}">
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="emergency_address" class="form-control" placeholder="Emergency Address"
                               value="{{ old('emergency_address') }}">
                    </div>
            
                    <div class="col-md-6">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control" accept=".jpg,.jpeg,.png">
                    </div>
            
                    <div class="col-12">
                        <label class="form-label">Signature (draw below)</label>
                        <canvas id="studentSignaturePad"></canvas>
                        <input type="hidden" name="student_signature" id="studentSignatureInput">
                        <button type="button" id="clearStudentSignature" class="btn btn-sm btn-outline-danger mt-2">Clear</button>
                    </div>
                </div>
            
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">Submit Student Registration</button>
                </div>
            </form>


            {{-- FACULTY & STAFF FORM --}}
            <form id="employeeForm" method="POST" action="{{ route('pendingEmployee.store') }}" enctype="multipart/form-data" class="hidden">
                @csrf
                <h5 class="mb-3">Faculty &amp; Staff Information</h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" name="firstname" class="form-control" value="{{ old('firstname') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="lastname" class="form-control" value="{{ old('lastname') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Middle Initial</label>
                        <input type="text" name="middle_initial" class="form-control" maxlength="16" value="{{ old('middle_initial') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Number</label>
                        <input type="text" name="employee_id" class="form-control" value="{{ old('employee_id') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" class="form-control" placeholder="e.g. Instructor I, Librarian"
                               value="{{ old('designation') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Select Program</label>
                        <select name="program" class="form-select" required>
                            <option value="">— Select program —</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->program_code }}" @selected(old('program') === $program->program_code)>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year of start of work in this HEI</label>
                        <select name="year_start_work" class="form-select" required>
                            <option value="">— Select year —</option>
                            @foreach ($workStartYears as $yr)
                                <option value="{{ $yr }}" @selected(old('year_start_work') == (string) $yr)>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Birthday</label>
                        <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="mobile_number" class="form-control" placeholder="09XXXXXXXXX"
                               value="{{ old('mobile_number') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Formal picture (optional)</label>
                        <input type="file" name="formal_picture" class="form-control" accept=".jpg,.jpeg,.png">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Signature (optional)</label>
                        <canvas id="employeeSignaturePad"></canvas>
                        <input type="hidden" name="employee_signature" id="employeeSignatureInput">
                        <button type="button" id="clearEmployeeSignature" class="btn btn-sm btn-outline-danger mt-2">Clear</button>
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="mb-3">Emergency Contact Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="emergency_contact_name" class="form-control" placeholder="Contact person"
                               value="{{ old('emergency_contact_name') }}">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="emergency_contact_relationship" class="form-control" placeholder="Relationship"
                               value="{{ old('emergency_contact_relationship') }}">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="emergency_contact_number" class="form-control" placeholder="Contact number"
                               value="{{ old('emergency_contact_number') }}">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="emergency_address" class="form-control" placeholder="Emergency address"
                               value="{{ old('emergency_address') }}">
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-success">Submit Faculty &amp; Staff Registration</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-sm">Back to home</a>
            </div>
        </div>
    </div>
</div>

<script>
// --- Signature Pad Setup ---
function setupSignaturePad(canvasId, inputId, clearBtnId) {
    const canvas = document.getElementById(canvasId);
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let points = [];

    function resizeCanvas() {
        const dataUrl = canvas.toDataURL();
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        const img = new Image();
        img.src = dataUrl;
        img.onload = () => ctx.drawImage(img, 0, 0);
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    canvas.style.touchAction = 'none';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function startDrawing(e) {
        e.preventDefault();
        drawing = true;
        points = []; // reset stroke ONLY, not the whole canvas
        points.push(getPos(e));
    }

    function draw(e) {
        e.preventDefault();
        if (!drawing) return;
    
        const pos = getPos(e);
        points.push(pos);
    
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
    
        // If just starting small stroke, draw a dot
        if (points.length === 1) {
            ctx.beginPath();
            ctx.arc(pos.x, pos.y, 1.5, 0, Math.PI * 2);
            ctx.fill();
            return;
        }
    
        ctx.beginPath();
    
        const last = points[points.length - 2];
        const dx = pos.x - last.x;
        const dy = pos.y - last.y;
        const speed = Math.sqrt(dx * dx + dy * dy);
    
        ctx.lineWidth = Math.max(1, 4 - speed / 2);
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(pos.x, pos.y);
    
        ctx.stroke();
    }


    function stopDrawing() {
        if (!drawing) return;
        drawing = false;
        document.getElementById(inputId).value = canvas.toDataURL();
    }

    // Event listeners
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    // Clear
    document.getElementById(clearBtnId).addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        points = [];
        document.getElementById(inputId).value = '';
    });

    return { resize: resizeCanvas };
}

// Initialize both
const studentPad = setupSignaturePad('studentSignaturePad', 'studentSignatureInput', 'clearStudentSignature');
const employeePad = setupSignaturePad('employeeSignaturePad', 'employeeSignatureInput', 'clearEmployeeSignature');

// --- Toggle Forms ---
const studentForm = document.getElementById('studentForm');
const employeeForm = document.getElementById('employeeForm');

function showRegisterTab(which) {
    const isStudent = which === 'student';
    studentForm.classList.toggle('hidden', !isStudent);
    employeeForm.classList.toggle('hidden', isStudent);
    document.getElementById('btnStudent').classList.toggle('btn-primary', isStudent);
    document.getElementById('btnStudent').classList.toggle('btn-outline-primary', !isStudent);
    document.getElementById('btnEmployee').classList.toggle('btn-success', !isStudent);
    document.getElementById('btnEmployee').classList.toggle('btn-outline-success', isStudent);
    setTimeout(() => (isStudent ? studentPad : employeePad).resize(), 50);
}

document.getElementById('btnStudent').addEventListener('click', () => showRegisterTab('student'));
document.getElementById('btnEmployee').addEventListener('click', () => showRegisterTab('employee'));

@if ($errors->has('employee_id') || $errors->has('designation') || $errors->has('program') || $errors->has('year_start_work') || old('designation'))
    showRegisterTab('employee');
@endif
</script>
</body>
</html>
