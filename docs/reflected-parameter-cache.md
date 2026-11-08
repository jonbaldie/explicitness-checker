# Reflected parameter cache

`ReflectedByReferenceParameters` returns snapshots. The cache is a persistent trie indexed by the hexadecimal SHA-256 digest of each lower-case name. An insertion copies only the nodes on its path. Earlier snapshots retain their own roots and cannot see later entries, including cached unknown names. A discarded descendant cannot make an earlier snapshot retain its new entries.

For C calls and K distinct names, cache operations take expected O(C + K) time and O(K) space for the latest snapshot. This assumes bounded name and parameter lengths, amortized constant-time hash operations, and a bounded number of full SHA-256 collisions. A path has at most 64 branches, each with at most 16 children. Reflection costs are separate. If callers retain earlier snapshots, their distinct copied paths also remain in memory. In the worst case, full-digest collisions put many names in one leaf and make insertion quadratic.

Run the isolated scaling check after `composer install`:

```sh
for n in 2000 4000 8000 16000 32000; do
    php tools/benchmark-reflection-cache.php "$n"
done
```

This check inserts generated unknown names and verifies a builtin by-reference parameter. It reports elapsed time and peak allocated memory without a timing threshold. It does not measure the end-to-end CLI or predict speedups for a particular project.
