<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Figure;
use App\Entity\FigureImages;
use App\Entity\FigureMedias;
use App\Form\CommentType;
use App\Form\FigureType;
use App\Security\Voter\FigureVoter;
use App\Service\CommentManager;
use App\Service\FigureManager;
use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FigureController extends AbstractController
{
    const FIGURES_PER_PAGE = 12;

    /**
     * @Route("/", name="app_home")
     */
    public function homePage(FigureManager $figureManager): Response
    {
        $figuresCount = $figureManager->countAllByStatus();
        $pageTotal = (int) ceil($figuresCount / self::FIGURES_PER_PAGE);

        return $this->render('figure/home.html.twig', [
            'figures' => $figureManager->findByStatusOrderByDateLimit(Figure::STATUS_ACCEPTED, self::FIGURES_PER_PAGE),
            'pageTotal' => $pageTotal,
        ]);
    }

    /**
     * @Route("/loadmore/{page<\d+>?1}", name="app_loadmore", methods={"GET"})
     */
    public function loadMore(FigureManager $figureManager, int $page = 1): Response
    {
        $beginAt = ($page - 1) * self::FIGURES_PER_PAGE;

        $content = $this->render('_parts/figure_grid.part.twig', [
            'figures' => $figureManager->findByStatusOrderByDateLimit(Figure::STATUS_ACCEPTED, self::FIGURES_PER_PAGE, $beginAt)
        ])->getContent();

        return new Response($content, 200, array('Content-Type' => 'text/html'));
    }

    /**
     * @Route("/figures", name="app_figure_index")
     */
    public function index(FigureManager $figureManager): Response
    {
        $figuresCount = $figureManager->countAllByStatus();
        $pageTotal = (int) ceil($figuresCount / self::FIGURES_PER_PAGE);

        return $this->render('figure/index.html.twig', [
            'figures' => $figureManager->findByStatusOrderByDateLimit(Figure::STATUS_ACCEPTED, self::FIGURES_PER_PAGE),
            'pageTotal' => $pageTotal,
        ]);
    }

    /**
     * @Route("/admin/figures/approvement", name="app_figure_approvement")
     */
    public function indexApprovement(FigureManager $figureManager): Response
    {
        return $this->render('figure/approvement.html.twig', [
            'figures' => $figureManager->findAllOrderByDate(),
        ]);
    }

    /**
     * @Route("/figure/show/{id<\d+>}-{slug}", name="app_figure_show")
     */
    public function show(Request $request, Figure $figure, CommentManager $commentManager): Response
    {
        $this->denyAccessUnlessGranted(FigureVoter::VIEW, $figure);

        $commentsCount = $commentManager->countAllByFigureAndStatus($figure);
        $commentsTotal = (int) ceil($commentsCount / CommentController::COMMENTS_PER_PAGE);

        $commentForm = $this->createForm(CommentType::class);
        $commentForm->handleRequest($request);
        $comment = new Comment();

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

            $comment->setContent($commentForm->getData()->getContent());
            $comment->setUser($this->getUser());
            $comment->setFigure($figure);

            $message = "Votre commentaire a bien été enregistré. Il sera vérifié par un membre de l'équipe d'ici deux jours ouvrés. Merci ! :)";

            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_MODO')) {
                $comment->setStatus(Comment::STATUS_ACCEPTED);
                $message = "Votre commentaire à été publié avec succès !";
            }

            $commentManager->add($comment);

            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_figure_show', [
                'id' => $figure->getId(),
                'slug' => $figure->getSlug(),
            ]);
        }

        return $this->render('figure/show.html.twig', [
            'figure' => $figure,
            'comments' => $commentManager->findAllByFigureAndStatusLimit($figure, Comment::STATUS_ACCEPTED, CommentController::COMMENTS_PER_PAGE),
            'commentsTotal' => $commentsTotal,
            'commentForm' => $commentForm->createView(),
        ]);
    }

    /**
     * @Route("/figure/new", name="app_figure_new")
     */
    public function new(Request $request, FigureManager $figureManager, FileUploader $fileUploader): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $media = new FigureMedias(); // Add a new blank media field
        $figure = new Figure();
        $figure->addFigureMedia($media);

        $form = $this->createForm(FigureType::class, $figure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images = $form->get('images')->getData();

            foreach ($images as $image) {
                /** @var UploadedFile $image */
                $fileName = $fileUploader->upload($image);

                $figureImage = new FigureImages();
                $figureImage->setFilename($fileName);

                $figure->addFigureImage($figureImage);
            }

            $user = $this->getUser();
            $figure->setUser($user);

            $message = "La figure a été enregistrée avec succès ! Elle sera relue et vérifiée par un administrateur d'ici deux jours ouvrés. :)";

            if ($this->isGranted('ROLE_MODO')) {
                $figure->setStatus(Figure::STATUS_ACCEPTED);
                $message = "Votre figure a été publiée avec succès !";
            }

            $figureManager->add($figure);

            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_figure_index');
        }
        return $this->render('figure/new.html.twig', [
            'figureType' => $form->createView()
        ]);
    }

    /**
     * @Route("/figure/edit/{id<\d+>}-{slug}/", name="app_figure_edit")
     */
    public function edit(Request $request, Figure $figure, FigureManager $figureManager, FileUploader $fileUploader): Response
    {
        $this->denyAccessUnlessGranted(FigureVoter::EDIT, $figure);

        $form = $this->createForm(FigureType::class, $figure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images = $form->get('images')->getData();

            foreach ($images as $image) {
                /** @var UploadedFile $image */
                $fileName = $fileUploader->upload($image);

                $figureImage = new FigureImages();
                $figureImage->setFilename($fileName);

                $figure->addFigureImage($figureImage);
            }
            $figure->setUpdatedAt(new \DateTimeImmutable());
            $figure->setStatus(Figure::STATUS_PENDING);

            $message = "La figure a été mise à jour avec succès ! Elle sera relue et vérifiée par un administrateur d'ici deux jours ouvrés.";

            if ($this->isGranted('ROLE_MODO')) {
                $figure->setStatus(Figure::STATUS_ACCEPTED);
                $message = "Votre figure a été mise à jour avec succès !";
            }

            $figureManager->add($figure);

            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_figure_index');
        }

        return $this->render('figure/edit.html.twig', [
            'figure' => $figure,
            'editForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/admin/figure/status/{id<\d+>}/{status}", name="app_figure_update_status")
     */
    public function updateStatus(Figure $figure, FigureManager $figureManager, $status): Response
    {
        $this->denyAccessUnlessGranted(FigureVoter::UPDATE_STATUS, $figure);

        $checkedStatus = $figureManager->checkStatus($status);

        if ($checkedStatus) {
            $figure->setStatus($checkedStatus['status']);
            $figureManager->add($figure);
            $this->addFlash('success', "La figure a bien été {$checkedStatus['label']}.");

            return $this->redirectToRoute('app_figure_approvement');
        }
        $this->addFlash('danger', "Le status de la figure n'est pas valide. Veuillez réessayer.");

        return $this->redirectToRoute('app_figure_approvement');
    }

    /**
     * @Route("/category/{id<\d+>}-{slug}", name="app_figures_by_category")
     */
    public function indexByCategory(Category $category, FigureManager $figureManager): Response
    {
        $figuresCount = $figureManager->countAllByStatusAndCategory($category);
        $pageTotal = (int) ceil($figuresCount / self::FIGURES_PER_PAGE);

        return $this->render('figure/index_by_category.html.twig', [
            'category' => $category,
            'pageTotal' => $pageTotal,
            'figures' => $figureManager->findAllByStatusAndCategoryOrderByDateLimit($category, Figure::STATUS_ACCEPTED, self::FIGURES_PER_PAGE),
        ]);
    }

    /**
     * @Route("/loadmore-bycategory/{id<\d+>}-{slug}/{page<\d+>?1}", name="app_loadmore_by_category", condition="request.isXmlHttpRequest()", methods={"GET"})
     */
    public function loadMoreByCategory(Category $category, FigureManager $figureManager, int $page = 1): Response
    {
        $beginAt = ($page - 1) * self::FIGURES_PER_PAGE;

        $content = $this->render('_parts/figure_grid.part.twig', [
            'figures' => $figureManager->findAllByStatusAndCategoryOrderByDateLimit($category, Figure::STATUS_ACCEPTED, self::FIGURES_PER_PAGE, $beginAt),
        ])->getContent();

        return new Response($content, 200, array('Content-Type' => 'text/html'));
    }

    /**
     * @Route("/figure/delete/{id<\d+>}", name="app_figure_delete", methods={"POST"})
     */
    public function delete(Request $request, Figure $figure, FigureManager $figureManager): Response
    {
        $this->denyAccessUnlessGranted(FigureVoter::DELETE, $figure);

        if ($this->isCsrfTokenValid('delete'.$figure->getId(), $request->request->get('_token'))) {
            $figureManager->delete($figure);
            $this->addFlash('success', "La figure a été supprimée avec succès");
        }

        return $this->redirectToRoute('app_figure_index');
    }
}
