<div class="sf-settings-wrap sf-float-wrap">
    <button onclick="toggleFloat(this)" title="{{$page->translate('settings')}}"
            class=" sf-icon-button sf-icon-button--icon radius-default sf-button-settings  sf-icon-button--size-1 sf-icon-button--link sf-icon-button--on-surface">
        <i class="sf-icon">settings</i>
    </button>
    <div class="sf-settings-menu bg-surface-overlay">
        [!Switch](size=1 title='{{$page->translate('dark')}}' on='{{$page->translate('on')}}'
        off='{{$page->translate('off')}}')#theme_switch
        [!Switch](size=1 title='{{$page->translate('wide')}}' on='{{$page->translate('on')}}'
        off='{{$page->translate('off')}}')#widescreen_switch
        <div id="size_switch" class="sf-font-size-control lang_size">
            <span class="sf-font-size-title">{{$page->translate('text size')}}</span>
            <div class="sf-font-size-options" role="group" aria-label="{{$page->translate('text size')}}">
                <button type="button" class="sf-font-size-option" data-font-index="0" data-font-size="14px" data-font-class="sf-font-small">{{$page->translate('reduced')}}</button>
                <button type="button" class="sf-font-size-option" data-font-index="1" data-font-size="16px" data-font-class="">{{$page->translate('default')}}</button>
                <button type="button" class="sf-font-size-option" data-font-index="2" data-font-size="18px" data-font-class="sf-font-big">{{$page->translate('increased')}}</button>
            </div>
        </div>
    </div>
</div>
