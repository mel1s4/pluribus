# Pluribus TODOs

## Community Features

### Offers & Requirements

- Poder desactivar / activar ofertas
- Poder darle un limite de disponibilidad a las ofertas
- Show options to add offer and requirement in the "my places" page

### Mobile & UX

- El mapa estorba cuando abro el sidenav en mobile

## Calendar and Posts

### Todos

- We want to copy the functionality of ../ToDoApp
- We will add two new post types: "task" (for todos), and "posts" (for events, announcements, information, etc)
- Users see a new "Tasks" page in their sidebar
- Ordered by folder (see ../ToDoApp)

### Calendar

- Community has a global calendar
- Each user can create new calendars, they can be private (shown only to the owner). 
- Calendars may be shared with other members of the community creating a "group", only members of that group can see and edit the calendar. 
- Users see a calendar page in the sidenav. See ../ToDoApp for inspiration

### Posts

- Events in the calendar are saved as "posts" 
- Users see a new page called posts on their left
- Posts also have a title, description, and content. Content may be written in markdown.
- Posts location is a point in the map, but they also have a "influence area" this is another optional field that lets users set a radius or polygon as the "influence" area of the post.  
- All posts are green-themed

### Map

- "posts" may have a location in the map as well
- Users may see posts in their map and filter them. 
- "Posts" and "Locations" can be filtered in the map if the filters are active.
- Move the Map filters to the left and order them vertically
- Add a comprehensive set of filters for the map.  
- Add support of those same filters in the backend
- When clicking a post in the map, it should show its influence area.
- "Posts" start datetime, enddatetime, and location information is not required.

### Groups

Users can unite in groups with two or more members. 
Groups have an owner, ownership can be delegated by the owner
Groups have "Name" 
Users can manage their groups from a new page called "My Groups" on the sidebar.
Here they can opt-out of their groups, see the groups information (shared folders, tasks, and posts) and create new groups

## Summary

- New pages: Tasks, Calendar, My Groups and Posts
- Tasks show only the Tasks
- Calendar and Map will become a "Discovery Pages" I mean in the way that they will show (and filter) more than one type of entity
- Calendar should show: tasks and posts
- Map should show: posts, and places
- We will also create the concept of "Groups". Here, two or more members can share "Folders", "Calendars", and "Tasks" with equal access.