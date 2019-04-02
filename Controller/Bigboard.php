<?php

namespace Kanboard\Plugin\Bigboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Formatter\BoardFormatter;
use Kanboard\Model\UserMetadataModel;

/**
  * Bigboard Controller.
  *
  * @author Thomas Stinner
  */
 class Bigboard extends BaseController
 {
     /**
      * Display a Board which contains multiple projects.
      */
     public function index()
     {
        $project_ids = $this->projectPermissionModel->getActiveProjectIds($this->userSession->getId());

         $search = urldecode($this->request->getStringParam('search'));

         #print $search;
//         ->withFilter(new TaskProjectsFilter(array_keys($projects)))

/*           $query = $this->taskLexer
            ->build($search)
            ->getQuery();

        print $query->buildSelectQuery(); */

 
          $nb_projects = count($project_ids);
           // Draw a header First
           $this->response->html($this->helper->layout->app('bigboard:board/show', array(
                'title' => t('Bigboard').' ('.$nb_projects.')',
                'board_selector' => false,
          )));

          echo $this->template->render('bigboard:board/dropdown', array(
            'bigboarddisplaymode' => $this->userMetadataCacheDecorator->get("BIGBOARD_COLLAPSED", 0) == 1,
            'bigboardprojectmode' => $this->userMetadataCacheDecorator->get("BIGBOARD_SHOWEMPTY", 0) == 1
        ));

        $filters = array(
        'controller' => "Bigboard",
        'action' => "index",
        'search' => $search,
        'plugin' => "Bigboard",
        );
          echo $this->template->render('bigboard:board/search', array(
              'filters' => $filters,
              'users_list' => $this->userModel->getActiveUsersList(),
          ));

          echo "<p>";

          $this->showProjects($project_ids);
     }

     /**
      * Show projects.
      *
      * @param $project_ids list of project ids to show
      *
      * @return bool
      */
     private function showProjects($project_ids)
     {
       print "<div id='bigboard'>";

       foreach ($project_ids as $project_id) {
             $project = $this->projectModel->getByIdWithOwner($project_id);
             $search = $this->helper->projectHeader->getSearchQuery($project);

             $this->userMetadataCacheDecorator->set(UserMetadataModel::KEY_BOARD_COLLAPSED.$project_id, 
                $this->userMetadataCacheDecorator->get("BIGBOARD_COLLAPSED", 0));

             $swimlanes = $this->taskLexer
                        ->build($search)
                        ->format(BoardFormatter::getInstance($this->container)->withProjectId($project['id']));

            if ($this->userMetadataCacheDecorator->get("BIGBOARD_SHOWEMPTY", 0) == 0) {
                # It's only necessary to calculate when empty projects are hidden.
                $nb_tasks = 0;
                foreach ($swimlanes as $index => $swimlane) {
                    $nb_tasks += $swimlane['nb_tasks'];
                }
            }

            if ($this->userMetadataCacheDecorator->get("BIGBOARD_SHOWEMPTY", 0) == 1 || $nb_tasks > 0)
            {
                echo $this->template->render('bigboard:board/view', array(
                'no_layout' => true,
                'board_selector' => false,
                'project' => $project,
                'title' => $project['name'],
                'description' => $this->helper->projectHeader->getDescription($project),
                'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
                'board_highlight_period' => $this->configModel->get('board_highlight_period'),
                'swimlanes' => $swimlanes,
                ));
            }
         }

         print "</div>";

     }

     public function collapseAll()
     {
         $this->changeDisplayMode(true);
     }

     public function expandAll()
     {
         $this->changeDisplayMode(false);
     }

     public function hideEmpty()
     {
        $this->changeProjectMode(false);
     }

     public function showEmpty()
     {
         $this->changeProjectMode(true);
     }

     private function changeProjectMode($mode)
     {
        $this->userMetadataCacheDecorator->set("BIGBOARD_SHOWEMPTY", $mode);

        if ($this->userSession->isAdmin()) {
            $project_ids = $this->projectModel->getAllIds();
        } else {
            $project_ids = $this->projectPermissionModel->getActiveProjectIds(session_get('user')['id']);
        }

        if ($this->request->isAjax()) {
            $this->showProjects($project_ids);
        } else {
            $this->response->redirect($this->helper->url->to('Bigboard', 'index', array('plugin' => 'Bigboard')));
        }
    }

    private function changeDisplayMode($mode)
    {
        $this->userMetadataCacheDecorator->set("BIGBOARD_COLLAPSED", $mode);

        if ($this->userSession->isAdmin()) {
             $project_ids = $this->projectModel->getAllIds();
        } else {
             $project_ids = $this->projectPermissionModel->getActiveProjectIds(session_get('user')['id']);
        }

        if ($this->request->isAjax()) {
            $this->showProjects($project_ids);
        } else {
            $this->response->redirect($this->helper->url->to('Bigboard', 'index', array('plugin' => 'Bigboard')));
        }
    }
 }
