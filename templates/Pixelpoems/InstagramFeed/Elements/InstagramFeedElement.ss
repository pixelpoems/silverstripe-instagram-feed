<% if $IsVisible %>

    <% if $ShowTitle %>
        <h2>$Title</h2>
    <% end_if %>

    <%  include Pixelpoems\InstagramFeed\Feed ReducedDisplay=$ReducedDisplay, DisplayCount=$DisplayCount %>
<% end_if %>

