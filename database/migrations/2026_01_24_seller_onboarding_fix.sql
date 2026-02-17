-- Cleanup duplicate rows per seller_id keeping newest by id
DELETE sb FROM seller_business sb
JOIN seller_business sb2 ON sb.seller_id = sb2.seller_id AND sb.id < sb2.id;

DELETE sl FROM seller_legal sl
JOIN seller_legal sl2 ON sl.seller_id = sl2.seller_id AND sl.id < sl2.id;

DELETE sd FROM seller_documents sd
JOIN seller_documents sd2 ON sd.seller_id = sd2.seller_id AND sd.id < sd2.id;

DELETE siv FROM seller_identity_verification siv
JOIN seller_identity_verification siv2 ON siv.seller_id = siv2.seller_id AND siv.id < siv2.id;

-- Add unique constraints to enforce one row per seller
ALTER TABLE seller_business
    ADD UNIQUE KEY seller_business_seller_id_unique (seller_id);

ALTER TABLE seller_legal
    ADD UNIQUE KEY seller_legal_seller_id_unique (seller_id);

ALTER TABLE seller_documents
    ADD UNIQUE KEY seller_documents_seller_id_unique (seller_id);

ALTER TABLE seller_identity_verification
    ADD UNIQUE KEY seller_identity_verification_seller_id_unique (seller_id);

-- Helpful indexes for admin/status queries
ALTER TABLE seller_identity_verification
    ADD INDEX idx_siv_status (verification_status),
    ADD INDEX idx_siv_police (police_verification_status);

ALTER TABLE seller_verification_logs
    ADD INDEX idx_svl_step (seller_id, step);
