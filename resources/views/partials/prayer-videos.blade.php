<ul class="ritual-videos">
    @foreach ($videos as $video)
        <li><a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noopener noreferrer"><span class="ritual-video-icon" aria-hidden="true">▶</span><span>{{ $video['title'] }} <span aria-hidden="true">↗</span></span></a></li>
    @endforeach
</ul>
