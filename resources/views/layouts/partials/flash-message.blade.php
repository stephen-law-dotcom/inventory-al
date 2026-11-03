@if (session('success'))
    <div class="notice success" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="notice error" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="notice error" role="alert">{{ $errors->first() }}</div>
@endif
