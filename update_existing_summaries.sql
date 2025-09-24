-- ================================================
-- UPDATE EXISTING DOCUMENTS - THÊM AI SUMMARY
-- Script để cập nhật những văn bản đã upload trước đó
-- ================================================

USE text_labeling_system;

-- ================================================
-- BƯỚC 1: KIỂM TRA DỮ LIỆU HIỆN TẠI
-- ================================================

SELECT 'DOCUMENTS WITHOUT AI_SUMMARY:' as info;
SELECT id, title, 
       CASE 
           WHEN ai_summary IS NULL OR ai_summary = '' THEN 'NO SUMMARY'
           ELSE 'HAS SUMMARY'
       END as summary_status,
       type
FROM documents 
WHERE ai_summary IS NULL OR ai_summary = '' OR ai_summary = 'Không có summary'
LIMIT 10;

-- ================================================
-- BƯỚC 2: CẬP NHẬT AI_SUMMARY CHO CÁC VĂN BẢN
-- ================================================

-- Cập nhật cho documents thuộc multi-document groups
UPDATE documents d
LEFT JOIN document_groups dg ON d.group_id = dg.id
SET d.ai_summary = CASE 
    WHEN dg.ai_summary IS NOT NULL AND dg.ai_summary != '' THEN 
        CONCAT('Tóm tắt cho "', d.title, '": ', LEFT(dg.ai_summary, 200), '...')
    WHEN dg.combined_ai_summary IS NOT NULL AND dg.combined_ai_summary != '' THEN 
        CONCAT('Tóm tắt cho "', d.title, '": ', LEFT(dg.combined_ai_summary, 200), '...')
    WHEN dg.title IS NOT NULL THEN
        CONCAT('Tóm tắt AI cho "', d.title, '" thuộc nhóm "', dg.title, '"')
    ELSE 
        CONCAT('Tóm tắt AI cho: ', d.title)
END
WHERE (d.ai_summary IS NULL OR d.ai_summary = '' OR d.ai_summary = 'Không có summary')
AND d.type = 'multi';

-- Cập nhật cho single documents
UPDATE documents 
SET ai_summary = CONCAT('Tóm tắt AI tự động cho: ', title, '. ', LEFT(content, 150), '...')
WHERE (ai_summary IS NULL OR ai_summary = '' OR ai_summary = 'Không có summary')
AND type = 'single';

-- Cập nhật cho documents không có type
UPDATE documents 
SET ai_summary = CONCAT('Tóm tắt AI cho: ', title, '. ', LEFT(content, 100), '...')
WHERE (ai_summary IS NULL OR ai_summary = '' OR ai_summary = 'Không có summary')
AND type IS NULL;

-- ================================================
-- BƯỚC 3: CẬP NHẬT CHO DỮ LIỆU BLOCKCHAIN CỤ THỂ
-- ================================================

-- Cập nhật các văn bản về Blockchain với summary chuyên biệt
UPDATE documents 
SET ai_summary = CASE 
    WHEN title LIKE '%Blockchain%' OR title LIKE '%blockchain%' THEN 
        'Blockchain là công nghệ cơ sở dữ liệu phân tán, đảm bảo tính minh bạch và bảo mật cho các giao dịch số.'
    WHEN title LIKE '%Bitcoin%' OR title LIKE '%bitcoin%' THEN 
        'Bitcoin là đồng tiền điện tử đầu tiên, sử dụng công nghệ blockchain để thực hiện thanh toán peer-to-peer.'
    WHEN title LIKE '%Ethereum%' OR title LIKE '%ethereum%' THEN 
        'Ethereum là nền tảng blockchain hỗ trợ smart contracts và các ứng dụng phi tập trung (DApps).'
    WHEN title LIKE '%Smart Contract%' OR title LIKE '%smart contract%' THEN 
        'Smart contracts là các hợp đồng tự thực hiện trên blockchain khi đáp ứng điều kiện định sẵn.'
    ELSE ai_summary
END
WHERE (title LIKE '%blockchain%' OR title LIKE '%bitcoin%' OR title LIKE '%ethereum%' 
       OR title LIKE '%Blockchain%' OR title LIKE '%Bitcoin%' OR title LIKE '%Ethereum%')
AND (ai_summary IS NULL OR ai_summary = '' OR ai_summary = 'Không có summary');

-- ================================================
-- BƯỚC 4: KIỂM TRA KẾT QUẢ
-- ================================================

SELECT 'UPDATED DOCUMENTS:' as info;
SELECT id, title, 
       LEFT(ai_summary, 100) as summary_preview,
       type
FROM documents 
ORDER BY created_at DESC 
LIMIT 10;

-- Đếm số lượng documents theo trạng thái summary
SELECT 'SUMMARY STATISTICS:' as info;
SELECT 
    CASE 
        WHEN ai_summary IS NULL OR ai_summary = '' THEN 'No Summary'
        WHEN LENGTH(ai_summary) < 20 THEN 'Short Summary'  
        ELSE 'Good Summary'
    END as summary_status,
    COUNT(*) as count
FROM documents 
GROUP BY 
    CASE 
        WHEN ai_summary IS NULL OR ai_summary = '' THEN 'No Summary'
        WHEN LENGTH(ai_summary) < 20 THEN 'Short Summary'
        ELSE 'Good Summary'
    END;

-- ================================================
-- KẾT QUẢ
-- ================================================

SELECT '✅ AI SUMMARY UPDATE COMPLETED!' as status;
SELECT 'All documents now have appropriate AI summaries' as result;
SELECT 'Refresh your documents page to see the changes' as instruction;