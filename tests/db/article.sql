CREATE TABLE `b_article` (
  `article_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '文章ID',
  `cate_id` int(10) unsigned NOT NULL COMMENT '分类ID',
  `title` varchar(64) NOT NULL COMMENT '标题',
  `user_id` int(10) unsigned NOT NULL COMMENT '作者ID',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `version` smallint(5) unsigned NOT NULL COMMENT '版本号',
  `comment_num` int(10) unsigned NOT NULL COMMENT '评论ID',
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9999 DEFAULT CHARSET=utf8 COMMENT='文章';