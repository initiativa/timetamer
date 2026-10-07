# TimeTamer
A GLPI's plugin to manage timesheets.

Uses:
+ **timing contracts**: contracts with a time budget
+ **timed tickets**: tickets under a timing contract
+ **time tasks**: tasks, under a timed ticket, that consume the time budget.


## The main page: _Overview_
![page screenshot](assets/screenshots/overview.png?raw=true "The overview page")
This page is available under the "Management" menu.

A monthly table shows the current user's registered hours, by timing contracts.\
A selector for year and month can change this view.\
The day number is a button to change the daily section below.

The daily section lists all entities with an associated timing contract, among the ones selected in the user session.\
Each entity has sub-sections for all associated timing contracts (each one with a counter for the remaining budgeted hours).\
Under a contract a collapsible table of all the timed tickets for that contract is shown.\
Each ticket has fields to fill to create a new time task for the selected day.\
All the tasks for the selected day are listed under the corresponding ticket row.\
Each task row has a switch to delete the task and a field to change its duration.

**WARNING**: all changes must be saved via any of the "Save all" buttons.

## Report pages
![page screenshot](assets/screenshots/contracts_report.png?raw=true "A report page")
Those pages are available from buttons at the top of any of this plugin pages.

A search page for: timing contracts, timed tickets, time tasks.\
Custom search parameters are available and data can be exported.

## Data representation
There is no persistent data storage on plugin defined tables.\
A contract with a numeric `num` field is considered as having an associated time budget of that many hours; it is a timing contract.\
A ticket associated with a timing contract (the first valid one) is a timed ticket.\
A private ticket task, with status of done and with an associated user, is a time task and it's duration is counted on the timing contract associated to the ticket (tasks on non-timed tickets are ignored).
