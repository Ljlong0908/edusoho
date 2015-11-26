<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;

class SearchController extends BaseController
{
    public function indexAction(Request $request)
    {
        $courses = $paginator = null;

        $currentUser = $this->getCurrentUser();

        $keywords = $request->query->get('q');
        $keywords = trim($keywords);

        $type    = $request->query->get('type', 'Course');
        $pattern = $this->setting('magic.cloud_search');

        if ($pattern) {
            return $this->redirect($this->generateUrl('cloud_search', array(
                'q'    => $keywords,
                'type' => $type
            )));
        }

        $vip = $this->getAppService()->findInstallApp('Vip');

        $isShowVipSearch = $vip && version_compare($vip['version'], "1.0.7", ">=");

        $currentUserVipLevel = "";
        $vipLevelIds         = "";

        if ($isShowVipSearch) {
            $currentUserVip      = $this->getVipService()->getMemberByUserId($currentUser['id']);
            $currentUserVipLevel = $this->getLevelService()->getLevel($currentUserVip['levelId']);
            $vipLevels           = $this->getLevelService()->findAllLevelsLessThanSeq($currentUserVipLevel['seq']);
            $vipLevelIds         = ArrayToolkit::column($vipLevels, "id");
        }

        $parentId   = 0;
        $categories = $this->getCategoryService()->findAllCategoriesByParentId($parentId);

        $categoryIds = array();

        foreach ($categories as $key => $category) {
            $categoryIds[$key] = $category['name'];
        }

        $categoryId = $request->query->get('categoryIds');
        $fliter     = $request->query->get('fliter');

        $conditions = array(
            'status'     => 'published',
            'title'      => $keywords,
            'categoryId' => $categoryId,
            'parentId'   => 0
        );

        if ($fliter == 'vip') {
            $conditions['vipLevelIds'] = $vipLevelIds;
        } elseif ($fliter == 'live') {
            $conditions['type'] = 'live';
        } elseif ($fliter == 'free') {
            $conditions['price'] = '0.00';
        }

        $count     = $this->getCourseService()->searchCourseCount($conditions);
        $paginator = new Paginator(
            $this->get('request'),
            $count
            , 12
        );
        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        return $this->render('TopxiaWebBundle:Search:index.html.twig', array(
            'courses'             => $courses,
            'paginator'           => $paginator,
            'keywords'            => $keywords,
            'isShowVipSearch'     => $isShowVipSearch,
            'currentUserVipLevel' => $currentUserVipLevel,
            'categoryIds'         => $categoryIds,
            'fliter'              => $fliter,
            'count'               => $count
        ));
    }

    public function cloudSearchAction(Request $request)
    {
        $courses = $paginator = null;

        $currentUser = $this->getCurrentUser();

        $keywords = $request->query->get('q');
        $keywords = trim($keywords);

        $type       = $request->query->get('type', 'Course');
        $page       = $request->query->get('page', '1');
        $conditions = array(
            'category'    => $type,
            'searchWords' => $keywords,
            'page'        => $page
        );

        if ($type == 'Teacher') {
            $conditions['category'] = 'User';
            $conditions['role']     = 'ROLE_TEACHER';
        }

        $counts = 0;
        try {
            list($resultSet, $counts) = $this->getSearchService()->cloudSearch($type, $conditions);
        } catch (\Exception $e) {
        }

        $paginator = new Paginator($this->get('request'), $counts, 10);

        return $this->render('TopxiaWebBundle:Search:cloud-search.html.twig', array(
            'keywords'  => $keywords,
            'type'      => $type,
            'resultSet' => $resultSet,
            'counts'    => $counts,
            'paginator' => $paginator
        ));
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getLevelService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.LevelService');
    }

    protected function getVipService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.VipService');
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.CategoryService');
    }

    protected function getSearchService()
    {
        return $this->getServiceKernel()->createService('Search.SearchService');
    }
}
