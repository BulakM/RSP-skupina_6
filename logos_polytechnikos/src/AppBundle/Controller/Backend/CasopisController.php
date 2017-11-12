<?php

namespace AppBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use AppBundle\Form\CasopisType;
use AppBundle\Form\CasopisFilterType;
use AppBundle\Entity\Casopis;
use AppBundle\Entity\Stav;

/**
  * @Route("/casopis")
  */
class CasopisController extends Controller
{
  /**
    * @Route("/index/{stav}", name="index_casopis_backend")
    */
    public function indexAction(Request $request, Stav $stav)
    {
      $filter_form = $this->createForm(CasopisFilterType::Class);
      $filter_form->handleRequest($request);

      $casopisy = $this->getDoctrine()->getRepository(Casopis::class)
          ->setPaginatorAndQueryUpdater(
              $this->get('knp_paginator'),
              $this->get('lexik_form_filter.query_builder_updater')
          )
          ->findAllWithPaginator(30, $request->query->getInt('page', 1), $filter_form, false, $stav->getId(), $this->getUser());

      return $this->render('backend/casopis/index.html.twig', array(
        'pagination' => $casopisy,
        'filter_form' => $filter_form->createView(),
        'stav' => $stav,
      ));
    }

  /**
    * @Route("/add", name="add_casopis")
    */
    public function addCasopisAction(Request $request)
    {
      $em = $this->getDoctrine()->getManager();

      $casopis = new Casopis($this->getUser(), $em->getReference(Stav::class, 0));

      $form = $this->createForm(CasopisType::class, $casopis);
      $form->handleRequest($request);

      if($form->isSubmitted() && $form->isValid())
      {
        $file = $casopis->getCasopis();

        $fileName = md5(uniqid()).'.'.$file->guessExtension();

        $file->move(
            $this->getParameter('casopis_directory'),
            $fileName
        );

        $casopis->setCasopis($fileName);

        $em = $this->getDoctrine()->getManager();
        $em->persist($casopis);
        $em->flush();

        $this->addFlash('notice', 'Časopis byl úspéšně odeslán.' );

        return $this->redirectToRoute('index_casopis_backend', ['stav' => $em->getReference(Stav::class, 0)]);
      }

      return $this->render(
            'backend/casopis/add.html.twig',
            [
                'form' => $form->createView(),
            ]
      );
    }

    /**
      * @Route("/edit/{casopis}", name="edit_casopis")
      */
      public function editCasopisAction(Request $request, Casopis $casopis)
      {
        $form = $this->createForm(CasopisType::class, $casopis);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
          $em = $this->getDoctrine()->getManager();
          $em->persist($casopis);
          $em->flush();

          $this->addFlash('notice', 'Časopis byl úspéšně odeslán.' );

          return $this->redirectToRoute('index_casopis_backend');
        }

        return $this->render(
              'backend/casopis/edit.html.twig',
              [
                  'form' => $form->createView(),
                  'casopis' => $casopis
              ]
        );
      }

    /**
     * @Route("/stav/{casopis}/{stav}", name="zmenit_stav_casopis")
     */
    public function zmenitStavAction(Request $request, Casopis $casopis, $stav)
    {
        $em = $this->getDoctrine()->getManager();

        switch($stav)
        {
          case -1:
            $stav = $em->getReference(Stav::class, -1); // Smazáno
            break;
          case 0:
            $stav = $em->getReference(Stav::class, 0); // Ke schválení
            break;
          case 1:
            $stav = $em->getReference(Stav::class, 1); // Schváleno
            break;
          case 2:
            $stav = $em->getReference(Stav::class, 2); // Publikováno
            break;
          default:
            $this->addFlash('error', 'Zadal jste neexistující stav');

            return $this->redirect($_SERVER['HTTP_REFERER']);
        }

        $casopis->setStav($stav);

        $em->persist($casopis);
        $em->flush();

        return $this->redirect($_SERVER['HTTP_REFERER']);
    }
}
