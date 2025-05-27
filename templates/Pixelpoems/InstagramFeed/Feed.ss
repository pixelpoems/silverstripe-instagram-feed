<div style="display: flex; flex-wrap: wrap; max-width: 80vw; grid-gap: 2rem; width: fit-content; margin: auto">
    <% loop $getFeed($ReducedDisplay, $DisplayCount) %>
        $Me
    <% end_loop %>
</div>
