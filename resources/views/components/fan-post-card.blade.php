@props(['post'])

<article class="fan-post-card">
    <img class="fan-post-image fan-post-image--{{ $post[6] }}" src="{{ asset('images/placeholders/'.$post[0].'.svg') }}" width="320" height="320" alt="Fan post illustration placeholder" loading="lazy">
    <div class="post-statistics">
        <span class="post-stat post-stat--views" aria-label="{{ number_format($post[3]) }} views">
            <svg aria-hidden="true"><use href="#icon-eye"/></svg><span>{{ number_format($post[3]) }}</span>
        </span>
        <span class="post-stat post-stat--recommendations" aria-label="{{ number_format($post[4]) }} recommendations">
            <svg aria-hidden="true"><use href="#icon-recommend"/></svg><span>{{ number_format($post[4]) }}</span>
        </span>
        <span class="post-stat post-stat--comments" aria-label="{{ number_format($post[5]) }} comments">
            <svg aria-hidden="true"><use href="#icon-comment"/></svg><span>{{ number_format($post[5]) }}</span>
        </span>
    </div>
    <div class="post-body">
        <div class="post-author">
            <img src="{{ asset('images/placeholders/'.$post[0].'.svg') }}" width="24" height="24" alt="" loading="lazy">
            <span>{{ $post[1] }}</span>
        </div>
        <p class="post-preview">{{ $post[2] }}</p>
    </div>
</article>
