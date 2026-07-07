<?php

namespace Pushword\Quiz\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Quiz\Entity\QuizResult;

/**
 * Read-only view of quiz usage: each row is one anonymous attempt (quiz, score,
 * host, date). Filter by quiz/host to read participation and score distribution.
 *
 * @extends AbstractCrudController<QuizResult>
 */
class QuizResultCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QuizResult::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('quiz.result.label.singular')
            ->setEntityLabelInPlural('quiz.result.label.plural')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // Anonymous data points: viewable and prunable, never hand-edited.
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('quiz')
            ->add('host')
            ->add('score')
            ->add('createdAt');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('quiz', 'quiz.result.field.quiz');
        yield IntegerField::new('score', 'quiz.result.field.score');
        yield TextField::new('result', 'quiz.result.field.result');
        yield TextField::new('host', 'quiz.result.field.host');
        yield DateTimeField::new('createdAt', 'quiz.result.field.createdAt');
    }
}
