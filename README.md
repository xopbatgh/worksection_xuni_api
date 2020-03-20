# Worksection Uni Api
Class that implements wrapper for official Worksection.com API and also create some extra API methods using http auth (imitates client web auth).

All the methods could be easily run from the class worksectionHandler

See the example at worksection_test.php. 

#### Currently supported and checked methods ####

Custom HTTP methods (details in classs worksectionHandler)
1. bool <b>isAuthed()</b> — check if http auth was successful
2. void <b>doHttpAuth()</b> — perform attemp to make http auth using config email and password
3. array <b>getLastEvents()</b> — return list of the last events for the current user
4. array <b>getTaskCommentsHtml($project_id, $task_id)</b> — return raw html task comments page
5. array <b>getTaskLogs($project_id, $task_id)</b> — return array with parsed logs from task comments page with info about tags added and their date

Official API methods (details in class worksectionCommonApi)
1. array <b>getLastEvents()</b> — return list of the last events for the current user
2. bool <b>subscribeToTask($task_page, $email_user)</b> — subscribe user with $email_user to task
3. array <b>getTaskComments($project_id, $task_id)</b> — return task comments list array
4. array <b>getProjects()</b> — get list of the all visible projects 
5. array <b>getAllTasks()</b> — get list of the all visible tasks
6. array <b>getProjectTasks(project_id, $task_id, $params = [])</b> — return the all project' tasks. Params optional: filter=active, text=1
7. string <b>generateApiUrl($page, $action, $extra_url_params = [])</b> — wrapper fucntion could be used to easily generate url for any official api method


Feel free to contribute
