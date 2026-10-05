# M3.1 source audit — preparation

Starting HEAD 9853110c985e0776381db59624db86cb7e044d98; source/runtime v265 (cb8f1ab). Existing editor independently reports v02.11.265, post5214 saved roots[], valid native JSON hash4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945, projected HTML bytes0. Fresh public/server confirmation remains pending before live writes.

| Boundary | Current gap / existing implementation |
|---|---|
| brief-ir.php intake | Team/Testimonial exact role slots and item.group_id already parsed; canonical groups are only synthesized for Services/Hero/About/Benefits/FAQ/Pricing. Extend existing groups, preserving exact spans and provenance. |
| design-plan.php schema/record resolution | migrated_create excludes Team/Testimonials. Existing grouped_items supplies cards, but consumes content again instead of canonical groups. Intro excludes body/actions. Canonical media validation currently permits only Hero/About media. |
| recipes.php records/editor projection | Neither family has canonical records or UI choices. Extend existing record catalog, with real grid/list structures and explicit profile compatibility. |
| elementor-ir.php card_nodes | Existing Team and Testimonial builders already bind name/position/bio and quote/author/meta/rating plus owner media. Reuse them; bind accepted collection/layout and item rhythm rather than legacy compiler choices. |
| llm.php ordinary route | migrated_active_create controls library retrieval bypass, accepted freeze and scoped transaction. Team/Testimonials currently miss this boundary; changing the enum alone would still leave groups, intro and media contract invalid. |
| design-plan.php validation | Existing ownership check catches cross-group text refs, but expected role map omits Team/Testimonials; media owner/cardinality/required policy needs family slot validation. |

Compatibility: historical frozen data remains unchanged. Process/CTA/Stats/Portfolio/forms/carousel/document scopes remain NOT_MIGRATED in this stage. No lifecycle/compiler/transaction rewrite is planned. Six live scenarios are NOT RUN; no new release prepared yet.
