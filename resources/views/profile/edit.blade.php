@extends('layouts.sidebar')

@section('title', 'My Profile')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1">My Profile</h1>
                <p class="text-muted mb-0">Manage your staff account details and password.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-1">Profile information</h2>
                        <p class="text-muted small mb-4">Your role and module permissions are managed by an administrator.</p>

                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PATCH')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="fname" class="form-label">First name</label>
                                    <input id="fname" name="fname" type="text"
                                        class="form-control @error('fname') is-invalid @enderror"
                                        value="{{ old('fname', $user->fname) }}" required autocomplete="given-name">
                                    @error('fname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="lname" class="form-label">Last name</label>
                                    <input id="lname" name="lname" type="text"
                                        class="form-control @error('lname') is-invalid @enderror"
                                        value="{{ old('lname', $user->lname) }}" required autocomplete="family-name">
                                    @error('lname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label for="email" class="form-label">Email address</label>
                                    <input id="email" name="email" type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $user->email) }}" required autocomplete="username">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control"
                                        value="{{ str_replace('_', ' ', ucfirst($user->role ?? 'staff')) }}" disabled>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 mt-4">
                                <button type="submit" class="btn btn-success">Save profile</button>
                                @if (session('status') === 'profile-updated')
                                    <span class="text-success small">Profile saved.</span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-1">Change password</h2>
                        <p class="text-muted small mb-4">Use at least eight characters and keep it unique.</p>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current password</label>
                                <input id="current_password" name="current_password" type="password"
                                    class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                    required autocomplete="current-password">
                                @error('current_password', 'updatePassword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New password</label>
                                <input id="password" name="password" type="password"
                                    class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                    required autocomplete="new-password">
                                @error('password', 'updatePassword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label">Confirm new password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password"
                                    class="form-control" required autocomplete="new-password">
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" class="btn btn-success">Update password</button>
                                @if (session('status') === 'password-updated')
                                    <span class="text-success small">Password updated.</span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
