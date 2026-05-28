@props(['game'])

<div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none]">
    @foreach(\App\Enums\GameHeroSectionEnum::cases() as $section)
        <a href="#{{ $section->value }}" class="neon-platform-pill whitespace-nowrap hover:border-cyan-400/40">{{ $section->label() }}</a>
    @endforeach
</div>
