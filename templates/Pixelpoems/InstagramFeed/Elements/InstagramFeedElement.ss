<% if $IsVisible %>

    <% if $ShowTitle %>
        <h2>$Title</h2>
    <% end_if %>

    <div style="display: flex; flex-wrap: wrap; max-width: 80vw; grid-gap: 2rem; width: fit-content; margin: auto">
        <% loop $Feed %>
            $Me
        <% end_loop %>
    </div>
<% end_if %>

