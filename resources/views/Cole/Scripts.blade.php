@if($Cole->EditMode)
<script type="text/javascript" src="{{ $Cole->Settings->ColeURL }}/Cole/ColeTools/edit.cole.js"></script>
<meta name="csrf-token" content="{{ csrf_token() }}">
@endif
