<%--This template is used to render a single instagram post when the type is "CAROUSEL_ALBUM"--%>
<a class="instagram-post" href="$Link" target="_blank">
    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
        <% loop $Children %>
            <img src="$MediaSrc" height="$DefaultSize" width="$DefaultSize"/>
        <% end_loop %>
    </div>
</a>
