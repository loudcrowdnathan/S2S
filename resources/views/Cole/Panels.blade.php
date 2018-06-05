<div class="Cole Panels">
    <div class="PanelListContainer">
        @isset($Cole->Page->PageMeta->Panels)
            @foreach($Cole->Page->PageMeta->Panels as $Panel)
                @isset($Panel->Blade)
                    <div class="Panel" data-paneluid="{{$Panel->Uid}}" data-panelid="{{$Panel->id}}">
                        @if($Cole->EditMode)            
                            <div class="Remove">
                                <i class="zmdi zmdi-delete"></i>
                            </div>
                        @endif
                        @include('Cole.Panels.'.$Panel->Blade)
                    </div>
                @endisset
            @endforeach
        @endisset
    </div>    
    @if($Cole->EditMode)
        <div class="New">
            <i class="zmdi zmdi-plus-circle"></i>
        </div>
    @endif
</div>