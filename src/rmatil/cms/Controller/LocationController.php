<?php

namespace rmatil\cms\Controller;

use SlimController\SlimController;
use rmatil\cms\Constants\HttpStatusCodes;
use rmatil\cms\Entities\Location;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\DBALException;
use DateTime;

class LocationController extends SlimController {

    private static $LOCATION_FULL_QUALIFIED_CLASSNAME = 'rmatil\cms\Entities\Location';
    private static $USER_FULL_QUALIFIED_CLASSNAME     = 'rmatil\cms\Entities\User';

    public function getlocationsAction() {
        $entityManager       = $this->app->entityManager;
        $locationRepository  = $entityManager->getRepository(self::$LOCATION_FULL_QUALIFIED_CLASSNAME);
        $locations           = $locationRepository->findAll();

        $this->app->response->header('Content-Type', 'application/json');
        $this->app->response->setStatus(HttpStatusCodes::OK);
        $this->app->response->setBody($this->app->serializer->serialize($locations, 'json'));
    }

    public function getLocationByIdAction($id) {
        $entityManager       = $this->app->entityManager;
        $locationRepository  = $entityManager->getRepository(self::$LOCATION_FULL_QUALIFIED_CLASSNAME);
        $location            = $locationRepository->findOneBy(array('id' => $id));

        if ($location === null) {
            $this->app->response->setStatus(HttpStatusCodes::NOT_FOUND);
            return;
        }

        // do not show lock if requested by the same user as currently locked
        if ($location->getIsLockedBy() !== null &&
            $location->getIsLockedBy()->getId() === $_SESSION['user']->getId()) {
            $location->setIsLockedBy(null);
        }

        $userRepository             = $entityManager->getRepository(self::$USER_FULL_QUALIFIED_CLASSNAME);
        $origUser                   = $userRepository->findOneBy(array('id' => $_SESSION['user']->getId()));
        $location->setAuthor($origUser);

        $this->app->response->header('Content-Type', 'application/json');
        $this->app->response->setStatus(HttpStatusCodes::OK);
        $this->app->response->setBody($this->app->serializer->serialize($location, 'json'));
        
        // set requesting user as lock
        $location->setIsLockedBy($origUser);

         // force update
        try {
            $entityManager->flush();
        } catch (DBALException $dbalex) {
            $now = new DateTime();
            $this->app->log->error(sprintf('[%s]: %s', $now->format('d-m-Y H:i:s'), $dbalex->getMessage()));
            $this->app->response->setStatus(HttpStatusCodes::CONFLICT);
            return;
        }
    }

    public function updateLocationAction($locationId) {
        $locationObject      = $this->app->serializer->deserialize($this->app->request->getBody(), self::$LOCATION_FULL_QUALIFIED_CLASSNAME, 'json');

        // get original location
        $entityManager       = $this->app->entityManager;
        $locationRepository  = $entityManager->getRepository(self::$LOCATION_FULL_QUALIFIED_CLASSNAME);
        $origLocation        = $locationRepository->findOneBy(array('id' => $locationId));

        $userRepository      = $entityManager->getRepository(self::$USER_FULL_QUALIFIED_CLASSNAME);
        $origUser            = $userRepository->findOneBy(array('id' => $_SESSION['user']->getId()));
        $locationObject->setAuthor($origUser);

        $origLocation->update($locationObject);
        // release lock on editing
        $origLocation->setIsLockedBy(null);

        // force update
        try {
            $entityManager->flush();
        } catch (DBALException $dbalex) {
            $now = new DateTime();
            $this->app->log->error(sprintf('[%s]: %s', $now->format('d-m-Y H:i:s'), $dbalex->getMessage()));
            $this->app->response->setStatus(HttpStatusCodes::CONFLICT);
            return;
        }

        $this->app->response->header('Content-Type', 'application/json');
        $this->app->response->setStatus(HttpStatusCodes::OK);
        $this->app->response->setBody($this->app->serializer->serialize($origLocation, 'json'));
    }

    public function insertLocationAction() {
        $locationObject      = $this->app->serializer->deserialize($this->app->request->getBody(), self::$LOCATION_FULL_QUALIFIED_CLASSNAME, 'json');

        // set now as creation date
        $now                = new DateTime();
        $locationObject->setLastEditDate($now);
        $locationObject->setCreationDate($now);

        $entityManager       = $this->app->entityManager;

        $userRepository      = $entityManager->getRepository(self::$USER_FULL_QUALIFIED_CLASSNAME);
        $origUser            = $userRepository->findOneBy(array('id' => $_SESSION['user']->getId()));
        $locationObject->setAuthor($origUser);

        $entityManager->persist($locationObject);

        try {
            $entityManager->flush();
        } catch(DBALException $dbalex) {
            $now = new DateTime();
            $this->app->log->error(sprintf('[%s]: %s', $now->format('d-m-Y H:i:s'), $dbalex->getMessage()));
            $this->app->response->setStatus(HttpStatusCodes::CONFLICT);
            return;
        }

        $this->app->response->header('Content-Type', 'application/json');
        $this->app->response->setStatus(HttpStatusCodes::CREATED);
        $this->app->response->setBody($this->app->serializer->serialize($locationObject, 'json'));
    }

    public function deleteLocationByIdAction($id) {
        $entityManager       = $this->app->entityManager;
        $locationRepository  = $entityManager->getRepository(self::$LOCATION_FULL_QUALIFIED_CLASSNAME);
        $location            = $locationRepository->findOneBy(array('id' => $id));

        if ($location === null) {
            $this->app->response->setStatus(HttpStatusCodes::NOT_FOUND);
            return;
        }

        $entityManager->remove($location);

        try {
            $entityManager->flush();
        } catch (DBALException $dbalex) {
            $now = new DateTime();
            $this->app->log->error(sprintf('[%s]: %s', $now->format('d-m-Y H:i:s'), $dbalex->getMessage()));
            $this->app->response->setStatus(HttpStatusCodes::CONFLICT);
        }

        $this->app->response->setStatus(HttpStatusCodes::NO_CONTENT);
    }

    public function getEmptyLocationAction() {
        $location = new Location();

        $entityManager              = $this->app->entityManager;
        $now                        = new DateTime();

        $userRepository             = $entityManager->getRepository(self::$USER_FULL_QUALIFIED_CLASSNAME);
        $origUser                   = $userRepository->findOneBy(array('id' => $_SESSION['user']->getId()));
        $location->setAuthor($origUser);

        $location->setCreationDate($now);
        $location->setLastEditDate($now);

        $this->app->response->header('Content-Type', 'application/json');
        $this->app->response->setStatus(HttpStatusCodes::OK);
        $this->app->response->setBody($this->app->serializer->serialize($location, 'json'));
    }
}