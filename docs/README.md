# Collection Documentation

[👈 Back to the project](../README.md)

## Guides

1. [Getting Started](getting-started.md) — installation, creating collections, conventions, exceptions, subclassing
2. [Paths: Dot-Notation & Wildcards](paths.md) — `user.name`, `users.*.email`, `users.*[id]`, `users[?age>18]`, `**.email`, objects, escaping
3. [Reading, Writing & Converting](reading-writing.md)
4. [Filtering & Finding](filtering.md)
5. [Transforming](transforming.md)
6. [Reshaping: rename, remap & cast](reshaping.md)
7. [Sorting & Grouping](sorting-grouping.md)
8. [Aggregates](aggregates.md)
9. [Slicing, Chunking & Randomness](slicing.md)
10. [Combining Collections](combining.md)
11. [Pipelines, Macros & Debugging](pipelines.md)
12. [Coming from Laravel](laravel.md)

## All methods

| Category | Methods |
|---|---|
| **Create** | [`new` / `make`](getting-started.md#make) · [`fromJson`](getting-started.md#fromjson) · [`wrap` / `unwrap` / `empty`](getting-started.md#wrap-unwrap-empty) · [`times` / `range`](getting-started.md#times-range) · [`collection()`](getting-started.md#creating-a-collection) |
| **Read** | [`get`](reading-writing.md#get) · [`collect`](reading-writing.md#collect) · [`all` / `toArray`](reading-writing.md#all--toarray) · [`keys`](reading-writing.md#keys) · [`values`](reading-writing.md#values) · [`count`](reading-writing.md#count) |
| **Check** | [`has` / `hasAll` / `hasKey`](reading-writing.md#has) · [`hasAny`](reading-writing.md#hasany) · [`isEmpty` / `isNotEmpty`](reading-writing.md#isempty--isnotempty) · [`isList` / `isAssoc`](reading-writing.md#islist--isassoc) |
| **Write** *(in place)* | [`set` / `put`](reading-writing.md#set--put) · [`getOrPut`](reading-writing.md#getorput) · [`forget` / `remove`](reading-writing.md#forget--remove) · [`pull`](reading-writing.md#pull) · [`push` / `add`](reading-writing.md#push--add) · [`prepend` / `unshift`](reading-writing.md#prepend--unshift) · [`pop` / `shift`](reading-writing.md#pop--shift) · [`splice`](reading-writing.md#splice) · [`transform`](reading-writing.md#transform) · [`clear`](reading-writing.md#clear) |
| **Copy** | [`insert`](reading-writing.md#insert) · [`copy`](reading-writing.md#copy) · [`toBase`](reading-writing.md#tobase) |
| **Convert** | [`toJson` / `toPrettyJson`](reading-writing.md#tojson--toprettyjson) · [`jsonSerialize`](reading-writing.md#jsonserialize) · [`escapeWhenCastingToString`](reading-writing.md#string-cast) |
| **Filter** | [`filter`](filtering.md#filter) · [`reject`](filtering.md#reject) · [`where`](filtering.md#where) · [`whereStrict`](filtering.md#wherestrict) · [`whereIn` / `whereNotIn` / `whereInStrict` / `whereNotInStrict`](filtering.md#wherein--wherenotin) · [`whereNull` / `whereNotNull`](filtering.md#wherenull--wherenotnull) · [`whereBetween` / `whereNotBetween`](filtering.md#wherebetween--wherenotbetween) · [`whereContains` / `whereStartsWith` / `whereEndsWith`](filtering.md#wherecontains--wherestartswith--whereendswith) · [`whereMatches`](filtering.md#wherematches) · [`whereInstanceOf`](filtering.md#whereinstanceof) · [`unique` / `uniqueStrict`](filtering.md#unique) · [`duplicates` / `duplicatesStrict`](filtering.md#duplicates) · [`takeUntil` / `takeWhile` / `skipUntil` / `skipWhile`](filtering.md#takeuntil--takewhile--skipuntil--skipwhile) |
| **Find** | [`first` / `last`](filtering.md#first--last) · [`firstWhere`](filtering.md#firstwhere) · [`firstOrFail`](filtering.md#firstorfail) · [`sole`](filtering.md#sole) · [`value`](filtering.md#value) · [`find`](filtering.md#find) · [`firstKey` / `lastKey`](filtering.md#firstkey--lastkey) · [`search`](filtering.md#search) · [`after` / `before`](filtering.md#after--before) |
| **Ask** | [`contains` / `some`](filtering.md#contains) · [`containsStrict`](filtering.md#containsstrict) · [`doesntContain` / `doesntContainStrict`](filtering.md#doesntcontain) · [`every`](filtering.md#every) · [`hasSole` / `hasMany`](filtering.md#hassole--hasmany) |
| **Map** | [`map`](transforming.md#map) · [`mapWithKeys`](transforming.md#mapwithkeys) · [`mapToDictionary` / `mapToGroups`](transforming.md#maptodictionary--maptogroups) · [`mapKeys`](transforming.md#mapkeys) · [`mapSpread`](transforming.md#mapspread) · [`mapInto`](transforming.md#mapinto) · [`flatMap`](transforming.md#flatmap) · [`filterMap`](transforming.md#filtermap) |
| **Paths in items** | [`mapPath`](transforming.md#mappath) · [`evolve`](transforming.md#evolve) · [`pluck`](transforming.md#pluck) · [`select`](transforming.md#select) · [`only` / `except`](transforming.md#only--except) |
| **Rename / remap / cast** | [`rename`](reshaping.md#rename) · [`remap`](reshaping.md#remap) · [`cast`](reshaping.md#cast) · [`castUsing`](reshaping.md#named-casters) |
| **Restructure** | [`flatten`](transforming.md#flatten) · [`collapse` / `collapseWithKeys`](transforming.md#collapse--collapsewithkeys) · [`dot` / `undot`](transforming.md#dot--undot) · [`flip`](transforming.md#flip) · [`reverse`](transforming.md#reverse) · [`transpose`](transforming.md#transpose) · [`toTree`](transforming.md#totree) |
| **Fold** | [`reduce`](transforming.md#reduce) · [`reduceSpread`](transforming.md#reducespread) · [`scan`](transforming.md#scan) · [`join`](transforming.md#join) · [`implode`](transforming.md#implode) |
| **Sort** | [`sort`](sorting-grouping.md#sort) · [`sortDesc`](sorting-grouping.md#sortdesc) · [`sortBy`](sorting-grouping.md#sortby) · [`sortByDesc`](sorting-grouping.md#sortbydesc) · [`sortKeys` / `sortKeysDesc`](sorting-grouping.md#sortkeys--sortkeysdesc) · [`sortKeysUsing` / `ksort`](sorting-grouping.md#sortkeysusing--ksort) |
| **Group** | [`groupBy`](sorting-grouping.md#groupby) · [`keyBy` / `mapBy`](sorting-grouping.md#keyby--mapby) · [`countBy`](sorting-grouping.md#countby) · [`partition`](sorting-grouping.md#partition) |
| **Aggregate** | [`sum`](aggregates.md#sum) · [`product`](aggregates.md#product) · [`avg` / `average`](aggregates.md#avg--average) · [`min` / `max`](aggregates.md#min--max) · [`minBy` / `maxBy`](aggregates.md#minby--maxby) · [`median`](aggregates.md#median) · [`mode`](aggregates.md#mode) · [`standardDeviation`](aggregates.md#standarddeviation) · [`percentage`](aggregates.md#percentage) |
| **Slice** | [`slice`](slicing.md#slice) · [`take` / `skip`](slicing.md#take--skip) · [`forPage`](slicing.md#forpage) · [`paginate`](slicing.md#paginate) · [`chunk`](slicing.md#chunk) · [`chunkWhile`](slicing.md#chunkwhile) · [`sliding`](slicing.md#sliding) · [`split` / `splitIn`](slicing.md#split--splitin) · [`nth`](slicing.md#nth) · [`pad`](slicing.md#pad) · [`shuffle`](slicing.md#shuffle) · [`random`](slicing.md#random) |
| **Combine** | [`merge`](combining.md#merge) · [`mergeRecursive`](combining.md#mergerecursive) · [`replace` / `replaceRecursive`](combining.md#replace--replacerecursive) · [`concat`](combining.md#concat) · [`union`](combining.md#union) · [`combine`](combining.md#combine) · [`diff*`](combining.md#diff) · [`intersect*`](combining.md#intersect) · [`symmetricDiff`](combining.md#symmetricdiff) · [`zip` / `unzip`](combining.md#zip--unzip) · [`crossJoin`](combining.md#crossjoin) · [`multiply`](combining.md#multiply) |
| **Pipeline** | [`each` / `eachSpread`](pipelines.md#each) · [`pipe` / `pipeInto` / `pipeThrough`](pipelines.md#pipe) · [`tap`](pipelines.md#tap) · [`when` / `unless`](pipelines.md#when--unless) · [`whenEmpty` & co.](pipelines.md#whenempty--whennotempty--unlessempty--unlessnotempty) · [`ensure`](pipelines.md#ensure) |
| **Extend** | [higher-order messages](pipelines.md#higher-order-messages) · [`macro` / `mixin` / `hasMacro` / `flushMacros`](pipelines.md#macros) · [`proxy`](pipelines.md#higher-order-messages) |
| **Debug** | [`dump` / `dd`](pipelines.md#debugging) |
