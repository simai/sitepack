<div id="search_doc" class="search--wrap flex content-main-end ml-auto w-0 sm:w-full text-end ">
    <script>
        window.sfSearchNotFound = '{{$page->translate('notFound')}}'
    </script>
    <div class="sf-input-container sf-input-container--1 sf-input-search-container grow-none relative">
        <div id="input_search" class="sf-input flex flex-col flex-nowrap gap-1/4 justify-start items-start sf-input--size-1 sf-input--decoration-bordered">
            <div class="sf-input-field flex flex-1 flex-row flex-nowrap justify-start items-center">
                <i class="sf-icon">search</i>
                <div class="sf-input-text-container flex flex-1 flex-row flex-nowrap justify-start items-center">
                    <div class="sf-input-group flex flex-col flex-nowrap justify-center items-start">
                        <input name="search" type="text" class="sf-input-main sf-input-text"
                               placeholder="{{$page->translate('search')}}">
                    </div>
                </div>
                <button
                    id="search_close"
                    type="button"
                    class="sf-close sf-close--size-1 hidden flex content-main-center items-cross-center cursor-pointer border-0 bg-transparent leading-1">
                    <i class="sf-icon"></i>
                </button>
            </div>
        </div>

        <div id="search_results" class="docsearch-input__holder hidden absolute inline-end-0 p-b6 radius-1">
            <div class="docsearch-input__main flex flex-col overflow-auto gap-1/4"></div>
        </div>
    </div>
</div>
