<?php

namespace App\Controller\Delegate;

use App\Entity\DamagedEducator;
use App\Entity\User;
use App\Form\ConfirmType;
use App\Form\DamagedEducatorEditType;
use App\Form\DamagedEducatorSearchType;
use App\Repository\DamagedEducatorPeriodRepository;
use App\Repository\DamagedEducatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DELEGATE')]
#[Route('/delegat', name: 'delegate_panel_')]
class PanelController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/odabir-perioda', name: 'damaged_educator_period')]
    public function damagedEducatorPeriod(DamagedEducatorPeriodRepository $damagedEducatorPeriodRepository): Response
    {
        $items = $damagedEducatorPeriodRepository->findBy([], [
            'year' => 'DESC',
            'month' => 'DESC',
        ]);

        return $this->render('delegate/damaged_educator_period.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/osteceni', name: 'damaged_educators')]
    public function damagedEducators(Request $request, DamagedEducatorPeriodRepository $damagedEducatorPeriodRepository, DamagedEducatorRepository $damagedEducatorRepository): Response
    {
        $periodId = $request->query->getInt('period');
        $period = $damagedEducatorPeriodRepository->find($periodId);
        if (empty($period)) {
            return $this->redirectToRoute('delegate_panel_damaged_educator_period');
        }

        $form = $this->createForm(DamagedEducatorSearchType::class, null, [
            'user' => $this->getUser(),
        ]);

        $form->handleRequest($request);
        $criteria = [];

        if ($form->isSubmitted()) {
            $criteria = $form->getData();
        }

        /** @var User $user */
        $user = $this->getUser();

        $criteria['schools'] = [];
        foreach ($user->getUserDelegateSchools() as $delegateSchool) {
            $criteria['schools'][] = $delegateSchool->getSchool()->getId();
        }

        $criteria['period'] = $period;
        $page = $request->query->getInt('page', 1);

        return $this->render('delegate/damaged_educators.html.twig', [
            'damagedEducators' => $damagedEducatorRepository->search($criteria, $page),
            'period' => $period,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/prijavi-ostecenog', name: 'new_damaged_educator')]
    public function newDamagedEducator(Request $request, DamagedEducatorPeriodRepository $damagedEducatorPeriodRepository): Response
    {
        $periodId = $request->query->getInt('period');
        $period = $damagedEducatorPeriodRepository->find($periodId);
        if (empty($period) || !$period->isActive()) {
            throw $this->createAccessDeniedException();
        }

        $damagedEducator = new DamagedEducator();
        $damagedEducator->setCreatedBy($this->getUser());
        $damagedEducator->setPeriod($period);

        $form = $this->createForm(DamagedEducatorEditType::class, $damagedEducator, [
            'user' => $this->getUser(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($damagedEducator);
            $this->entityManager->flush();

            $this->addFlash('success', 'Uspešno ste sačuvali oštećenog.');

            return $this->redirectToRoute('delegate_panel_damaged_educators', [
                'period' => $damagedEducator->getPeriod()->getId(),
            ]);
        }

        return $this->render('delegate/edit_damaged_educator.html.twig', [
            'form' => $form->createView(),
            'damagedEducator' => $damagedEducator,
        ]);
    }

    #[Route('/osteceni/{id}/izmeni-podatke', name: 'edit_damaged_educator')]
    public function editDamagedEducator(Request $request, DamagedEducator $damagedEducator): Response
    {
        if (!$damagedEducator->getPeriod()->isActive()) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        $allowedSchools = [];
        foreach ($user->getUserDelegateSchools() as $delegateSchool) {
            $allowedSchools[] = $delegateSchool->getSchool()->getId();
        }

        if (!in_array($damagedEducator->getSchool()->getId(), $allowedSchools)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(DamagedEducatorEditType::class, $damagedEducator, [
            'user' => $this->getUser(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $damagedEducator->setCreatedBy($this->getUser());
            $this->entityManager->persist($damagedEducator);
            $this->entityManager->flush();

            $this->addFlash('success', 'Uspešno ste izmenili podatke od oštećenog.');

            return $this->redirectToRoute('delegate_panel_damaged_educators', [
                'period' => $damagedEducator->getPeriod()->getId(),
            ]);
        }

        return $this->render('delegate/edit_damaged_educator.html.twig', [
            'form' => $form->createView(),
            'damagedEducator' => $damagedEducator,
        ]);
    }

    #[Route('/osteceni/{id}/brisanje', name: 'delete_damaged_educator')]
    public function deleteDamagedEducator(Request $request, DamagedEducator $damagedEducator): Response
    {
        if (!$damagedEducator->getPeriod()->isActive()) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        $allowedSchools = [];
        foreach ($user->getUserDelegateSchools() as $delegateSchool) {
            $allowedSchools[] = $delegateSchool->getSchool()->getId();
        }

        if (!in_array($damagedEducator->getSchool()->getId(), $allowedSchools)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ConfirmType::class, null, [
            'message' => 'Potvrđujem da želim da obrišem oštećenog "'.$damagedEducator->getName().'".',
            'submit_message' => 'Potvrdi',
            'submit_class' => 'btn btn-error',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($damagedEducator);
            $this->entityManager->flush();

            $this->addFlash('success', 'Uspešno ste obrisali oštećenog.');

            return $this->redirectToRoute('delegate_panel_damaged_educators', [
                'period' => $damagedEducator->getPeriod()->getId(),
            ]);
        }

        return $this->render('delegate/delete_damaged_educator.html.twig', [
            'form' => $form->createView(),
            'damagedEducator' => $damagedEducator,
        ]);
    }
}
