<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FunctionAnalysis;
use JonBaldie\ExplicitnessChecker\FunctionResult;

/** Directional reachability over declarations in exactly the selected files. */
final class ProjectAnalysis
{
    /** @param list<FileCheckResult> $files
     *  @return list<FileCheckResult>
     */
    public function propagate(array $files): array
    {
        $index = new ProjectIndex($files);
        $edges = [];
        $states = [];
        foreach ($index->functions() as $id => $function) {
            $edges[$id] = ProjectCalls::from($function, $index);
            $direct = $function->getResult();
            $states[$id] = ($direct->getInputs() !== [] ? 1 : 0) | ($direct->getOutputs() !== [] ? 2 : 0);
        }
        $states = $this->reach($states, $edges);
        $results = [];
        foreach ($index->functions() as $id => $function) {
            $results[$id] = $this->result($function->getResult(), $edges[$id], $states);
        }

        return $this->files($files, $results);
    }

    /** @param array<string, int> $states
     *  @param array<string, array<string, CallEdge>> $edges
     *  @return array<string, int>
     */
    protected function reach(array $states, array $edges): array
    {
        do {
            $changed = false;
            foreach ($edges as $id => $calls) {
                foreach ($calls as $call) {
                    $next = $states[$id] | $states[$call->getCallee()->getId()];
                    if ($next !== $states[$id]) {
                        $states[$id] = $next;
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        return $states;
    }

    /** @param array<string, CallEdge> $edges
     *  @param array<string, int> $states
     */
    protected function result(FunctionResult $direct, array $edges, array $states): FunctionResult
    {
        $findings = $direct->getFindings();
        foreach ($edges as $edge) {
            $callee = $edge->getCallee();
            $subject = 'calls action ' . $callee->getResult()->getName() . '() (line ' . $callee->getResult()->getLine() . '): implicit ';
            foreach ([1 => 'input', 2 => 'output'] as $bit => $direction) {
                if (($states[$callee->getId()] & $bit) !== 0) {
                    $findings[] = new Finding($subject . $direction, Category::CALLS_ACTION, $edge->getLine(), null, $bit === 2);
                }
            }
        }
        $analysis = $direct->getAnalysis();

        return new FunctionResult($direct->getName(), $direct->getLine(), new FunctionAnalysis(
            $findings, $analysis->getParameters(), $analysis->getDeclaredGlobals(),
        ));
    }

    /** @param list<FileCheckResult> $files
     *  @param array<string, FunctionResult> $results
     *  @return list<FileCheckResult>
     */
    protected function files(array $files, array $results): array
    {
        $updated = [];
        foreach ($files as $file) {
            $functions = [];
            foreach ($file->getFunctions() as $position => $direct) {
                $functions[] = $results[ProjectIndex::identity($file->getFile(), $position)] ?? $direct;
            }
            $updated[] = new FileCheckResult($file->getFile(), $functions, $file->getUnchecked(), $file->getAst());
        }

        return $updated;
    }
}
