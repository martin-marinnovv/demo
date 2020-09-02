<?php

namespace App\Controller\Api;
use App\Controller\Api\BaseController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Entity\User;
use App\Entity\UserAch;
use Psr\Log\LoggerInterface;


/**
 */

class UserController extends BaseController
{

    /**
     * Update user 
     * @Rest\Patch("/user", name="api_u_p")
     * @Rest\View(serializerGroups={"userself"})
     * @param Request $request
     */
    public function patchUser(Request $request,LoggerInterface $logger): View
    {
        $data = json_decode($request->getContent(), true);
        if (! isset ($data)){
            throw new HttpException(400, "No valid data in request");
        }
        $logger->warn("-----------");
        $logger->warn(print_r($data, true));
        $logger->warn("-----------");
        
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(User::class);
        $repo_user = $this->getDoctrine()->getRepository(User::class)->findOneByUuid($user->getUuid());

        /*
        if ($user->getDid() <> "" AND isset($data['did']) AND $data['did'] <> $repo_user->getDid() ){            
            throw new HttpException(401, "Unauthorized. New device ID");
        }
        */

        if (isset($data['did'])){  $user->setDid($data['did']); }
        if (isset($data['name'])){  $user->setName($data['name']); }
        if (isset($data['email'])){  $user->setEmail($data['email']); }
        if (isset($data['moto'])){  $user->setMoto($data['moto']); }
        if (isset($data['photo'])){  $user->setPhoto($data['photo']); }
        if (isset($data['show'])){  $user->setShow($data['show']); }
        if (isset($data['showname'])){  $user->setShowName($data['showname']); }

        if (isset($data['country'])){  $user->setCountry($data['country']); }
        if (isset($data['city'])){  $user->setCity($data['city']); }
        if (isset($data['age'])){  $user->setAge($data['age']); }
        
        if (isset($data['dpt'])){  $user->setDpt($data['dpt']); } 
        
        $repo->save($user);

        return View::create($user, Response::HTTP_OK);
        
    }

    /**
     * Get user 
     * @Rest\Get("/user", name="api_u_g")
     * @Rest\View(serializerGroups={"userself"})
     */
    public function getMe(): View
    {
        $user = $this->getUser();
        return View::create($user, Response::HTTP_OK);

    }

    /**
     * Get user streak
     * @Rest\Get("/user/streak", name="api_u_g_s")
     * @Rest\View(serializerGroups={"streak"})
     */
    public function getMyStreak(): View
    {
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(UserAch::class);

        $result = array();

        //Dayly ach
        $d = new \DateTime();
        $d->setTimestamp(strtotime('today midnight'));
        $ach = $repo->findSumForUserFromDate($user->getId(), $d->format('Y-m-d'));
        $result['cnt_day'] = $ach['D'];


        //Weekly ach
        $d = new \DateTime();
        $d->setTimestamp(strtotime('-7 days'));
        $ach = $repo->findSumForUserFromDate($user->getId(), $d->format('Y-m-d'));
        $result['cnt_week'] = $ach['D'];

        //Montly ach
        $d = new \DateTime;
        $d->setTimestamp(strtotime('-30 days'));
        $ach = $repo->findSumForUserFromDate($user->getId(), $d->format('Y-m-d'));
        $result['cnt_month'] = $ach['D'];

        //All ach
        $d = new \DateTime('2019-01-01');
        $ach = $repo->findSumForUserFromDate($user->getId(), $d);
        $result['cnt_all'] = $ach['D'];

        $ach = $repo->findAllForUserFromDate($user->getId(), $d);
        $result['ach'] = $ach;

        //Streak
        $ach = $repo->findAllForUserFromDateGrouped($user->getId(), $d);
        

        $streak = 0;
        $d = 0;
        foreach ($ach as $a){
            if ($d === 0){ $d = $a['day'];}

            $diff = $a['day']->diff($d)->format("%a");
            if ($diff > 1){ break;}

            $streak = $streak +1;
            $d = $a['day'];

        }
        $result['streak'] = $streak;

        return View::create($result, Response::HTTP_OK);

    }


    /**
     * Update user ach
     * @Rest\Post("/user/ach", name="api_u_ach")
     * @Rest\View(serializerGroups={"userself"})
     * @param Request $request
     */
    public function postAch(Request $request,LoggerInterface $logger): View
    {
        $data = json_decode($request->getContent(), true);
        if (! isset ($data)){
            throw new HttpException(400, "No valid data in request");
        }

        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(UserAch::class);
        $em = $this->getDoctrine()->getManager();

        foreach ($data as $a){
            if (isset($a['date']) and isset($a['duration'])){
                // No longer needed.
                //$ach = $repo->findOneByUserAndDate($user->getId(), (new \DateTime($a['date']))->format('Y-m-d'));
                //if (! $ach) {
                    $ach = new UserAch();
                    $ach->setDay(new \DateTime($a['date']));
                    $ach->setUser($user);
                    $logger->warn("-----------");
                    //$logger->warn(print_r($ach, true));
                //}
                $ach->setDuration($a['duration']);
                $ach->setTsession($a['tsession']);
                $ach->setCounts($a['counts']);
                $em->persist($ach);
            }else{
                throw new HttpException(400, "Invalid data in request");  
            }

        }
        $em->flush();
        $logger->warn("-----------");
        $logger->warn(print_r($data, true));
        $logger->warn("-----------");
        
        
        return View::create($user, Response::HTTP_OK);

    }

    /**
     * Get user daily ach
     * @Rest\Get("/user/daily-ach-bytsession", name="api_u_g_da")
     * Rest\View(serializerGroups={"streak"})
     */
    public function getMyDailyAch(): View
    {
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(UserAch::class);

        $d = new \DateTime();
        $ach = $repo->findMaxForUserGroupedByTsession($user->getId());
        //var_dump($ach);
        return View::create($ach, Response::HTTP_OK);
    }

    /**
     * Get ach count by day
     * @Rest\Get("/user/ach-cnt-byday", name="api_u_g_abd")
     * Rest\View(serializerGroups={"streak"})
     */
    public function getAchByDay(): View
    {
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(UserAch::class);

        $d = new \DateTime();
        $ach = $repo->findCntForUserGroupedByDay($user->getId());

        if ($ach){

        } else {
            $array[] = array ('day' => $d, 'cnt' => 0);
            return View::create(array_reverse($array), Response::HTTP_OK);

        }

        // Find min max dates
        $array = array();
        $start = $ach[count($ach) - 1]['day'];
        $interval = new \DateInterval('P1D');
        $end = new \DateTime();
        //$end->add($interval);
        $period = new \DatePeriod($start,$interval,$end);

        foreach($period as $date) { 
            $dte = $date->format('Y-m-d');
            $cnt = "0";

            foreach ($ach as $a){
                 if ($a['day']->format('Y-m-d') == $dte){   $cnt = $a['cnt'];}
            }
            
            
            $array[] = array ('day' => $date, 'cnt' => $cnt); 
        }


        return View::create(array_reverse($array), Response::HTTP_OK);
    }

    /**
     * Get ach per day
     * @Rest\Get("/user/ach-perday/{day}" ,name="api_u_g_apd")
     */
    public function getAchPerDay(Request $request, string $day): View
    {
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getRepository(UserAch::class);

        $d = new \DateTime();
        $ach = $repo->findAllForUserPerDayGroupedByTsession($user->getId(), $day);
        //$ach = $repo->findAllForUserPerDate($user->getId(), $day);
        return View::create(array_reverse($ach), Response::HTTP_OK);
    }

    /**
     * New payment for user 
     * @Rest\Post("/user/payment", name="api_up_p")
     * @Rest\View(serializerGroups={"userself"})
     * @param Request $request
     */
    public function newPayment(Request $request): View
    {
        $data = json_decode($request->getContent(), true);
        if (! isset ($data)){
            throw new HttpException(400, "No valid data in request");
        }

       
        $user = $this->getUser();
        $payment = new UserPayment();
        $user->addPayment($payment);
    
        
        //$repo->save($user);

        return View::create($user, Response::HTTP_OK);
        
    }


    

}
