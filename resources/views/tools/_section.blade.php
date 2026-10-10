{{-- A step of the tool page that only one tool has (ParametricGenerator::SECTIONS): what the tool placed in it, then the tool's own block for it, if it has one. $step = the step's id --}}
<x-tool-section :id="$step" :title="$sections[$step]">
    @include('tools._placed', ['step' => $step])
    @includeIf('tools._'.$kind.'_'.$step)
</x-tool-section>
