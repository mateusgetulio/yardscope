# Evals

The model is not covered by the invariants; it is measured. Twelve labeled sets under `evals/sets` cover the scenarios in the plan: happy path (two), cleanup plus shrubs to count (two), missing wide shot (two), requested service unseen, unusable photo, branch uncertainty, manual-only service, hallucination trap, multiple sections. The photos are openly licensed images found through Openverse; every title, creator, license and source is in `evals/LICENSES.md`, and several sets combine photos of different properties and say so in their `notes`.

`php artisan yardscope:eval --live` runs every set through the configured extractor, records each answer under `evals/fixtures`, and writes `evals/results/<date-time>.json` with the metrics and, per set, the parsed answer and every comparison. `--fixtures` replays the recordings; `--fixtures --expect latest` also fails when the metrics, readiness, dispositions or hallucinations drift from the newest committed results file, which is what CI runs. Every run is kept as written.

## How the numbers are computed

- A **service** is a line's type and section. Each labeled line is matched at most once; a line the model reports twice counts as a duplicate, not a second match.
- **Precision** credits lines that match a labeled line, including lines marked `optional` (visible in the photos but not asked for); **recall** counts only the required lines. **Hallucinated lines** are lines the model produced with no support in the labels. Placeholders the pipeline itself adds for requested work no photo shows (rule R3) are never counted as model lines; they are scored on their disposition and their photo request.
- Counts are exact or within one; severity, size and the counting photo are compared per line; readiness and the photo request per set. A rate with nothing to measure is null.

## The runs

All three runs on 2026-09-14 went through the Claude Code driver on the developer's session.

| Metric | Run 1 | Run 2 | Run 3 as run | Run 3 re-scored |
|---|---|---|---|---|
| Schema-valid answers | 12/12 | 12/12 | 12/12 | 12/12 |
| Service precision / recall | 0.810 / 0.895 | 1.000 / 1.000 | 0.929 / 0.684 | 1.000 / 0.933 |
| Counts exact / within one | 0.800 / 0.800 | 1.000 / 1.000 | 0.600 / 0.800 | 0.800 / 1.000 |
| Hallucinated lines | 4 | 0 | 1 | 0 |
| Severity / size correct | 0.889 / 0.250 | 0.889 / 0.750 | 0.889 / 0.500 | 0.889 / 0.750 |
| Dispositions correct | 0.474 | 0.895 | 0.737 | 0.789 |
| Request readiness correct | 7/12 | 10/12 | 9/12 | 9/12 |
| Photo request correct | 0.500 | 1.000 | 0.750 | 0.750 |
| Unusable photos flagged | 0.917 | 0.917 | 0.917 | 1.000 |

What changed between the runs, and what each change did:

- **Run 1** was scored by the first version of the scorer, whose recordings were overwritten by run 2, so it cannot be replayed; its results file has the per-set comparisons but not the answers. It found a real problem: the model reported ordinary overhead power lines as a hazard in five sets, and rule R5 then sent whole sections to a pro quote. That accounts for the disposition misses in sets 01, 02, 03 and 12 and the readiness misses in 01, 02 and 12.
- **Between run 1 and run 2** the hazard instruction was tightened to name what makes the work itself unsafe on the ground, and four labels were edited after seeing the answers. Two of the "missing wide shot" sets had expected a line with a section where the pipeline produces a section-less placeholder; that was a labeling mistake and explains two of run 1's four "hallucinated" lines and its photo-request misses. Set 10's shrub was relabeled from front yard to side yard, which matches the photo (driveway, bin, house wall) and also matches what the model answered, so that one is a re-judgment made with the model's answer in view. Sets 02 and 10 gained optional lines for beds and shrubs that are visible but were not requested, which removed the other two run-1 hallucinations. So the precision, recall and hallucination gains between the first two runs came from the labels; the disposition and readiness gains came from the instruction.
- **Between run 2 and run 3** the scorer changed (observed placeholders no longer count as model lines, duplicates no longer count as matches, the answers are stored) and the two missing-wide-shot sets were rebuilt so one of them exercises section coverage (rule R2) rather than the placeholder path. Run 2's recordings were overwritten by run 3, so only run 3 replays.
- **Run 3 as run** still had two problems of its own, both on the scoring side. Rebuilding the sets had silently reverted the label corrections to sets 02 and 10, and the scorer excluded observed placeholders from the service lists but still counted labeled placeholders as required lines, so a correctly produced placeholder was scored as a missed service (three sets, recall 0.684). Set 06's unusable-photo label was also wrong by the instruction's own definition (in focus and showing a yard): two close-ups of a flower do not show a yard. **Run 3 re-scored** is the same twelve answers replayed under the corrected labels and scorer; the file records mode `fixtures` and the answers are identical, which the drift guard checks.

## Misses in run 3, re-scored

- **03 cleanup plus hibiscus.** The model counted six hibiscus shrubs and called them large; the label says five and medium. Large shrubs go to a pro, so the request is partial instead of ready. The count is one off and the size is a judgment call the photo does not settle.
- **05 missing wide shot, beds.** The model called the first close view a medium view of the backyard, gave the bed a count without a counting view, and the line was rejected (rule R4) rather than gated for coverage (rule R2). The request still ends on needs photos, but through the placeholder, whose photo request does not name the section. The set does not exercise R2 the way it was meant to; R2 is covered by the unit tests.
- **09 branch.** The model rates the storm-debris cleanup moderate where the label says heavy, and reports two hazards: the fallen limb propped at a steep angle against the structure, and an extension cord on the ground beside it in photo 2. Both are defensible, and rule R5 sends the cleanup to a pro along with the branch, so the request is a pro quote instead of partial.
- **12 multiple sections.** The model calls the side-yard bed photo a close view, so the bed needs a wider photo before it can be priced; the label calls it medium. The two cleanups are priced, so the request is partial instead of ready.

The photo-request miss is set 05 above; the size misses are sets 03 and 05.
